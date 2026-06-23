#!/usr/bin/env python3
"""
Notification Worker — Consume event dari RabbitMQ dan log ke file/stdout

Event yang dikonsumsi:
- harvest.ready  : Prediksi panen dalam 7 hari
- alert.pest     : Deteksi hama/penyakit oleh ML

Output: Log file + stdout (stdout akan muncul di docker logs)
"""

import json
import logging
import os
import time
import pika
import mysql.connector
from datetime import datetime

# ── Konfigurasi Logging ────────────────────────────────────────
LOG_DIR = '/app/logs'
os.makedirs(LOG_DIR, exist_ok=True)

log_formatter = logging.Formatter(
    fmt='%(asctime)s [%(levelname)s] %(name)s → %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)

# Handler untuk file
file_handler = logging.FileHandler(f'{LOG_DIR}/notification_worker.log')
file_handler.setFormatter(log_formatter)

# Handler untuk stdout (docker logs)
console_handler = logging.StreamHandler()
console_handler.setFormatter(log_formatter)

logger = logging.getLogger('NotificationWorker')
logger.setLevel(logging.INFO)
logger.addHandler(file_handler)
logger.addHandler(console_handler)

# ── Konfigurasi ────────────────────────────────────────────────
RABBITMQ_HOST = os.getenv('RABBITMQ_HOST', 'rabbitmq')
RABBITMQ_PORT = int(os.getenv('RABBITMQ_PORT', '5672'))
RABBITMQ_USER = os.getenv('RABBITMQ_USERNAME', 'guest')
RABBITMQ_PASS = os.getenv('RABBITMQ_PASSWORD', 'guest')

DB_HOST = os.getenv('DB_HOST', 'mysql')
DB_PORT = int(os.getenv('DB_PORT', '3306'))
DB_USER = os.getenv('DB_USERNAME', 'agri_user')
DB_PASS = os.getenv('DB_PASSWORD', 'rootpass')
DB_NAME = os.getenv('DB_DATABASE', 'agriCity')


def get_db_connection():
    """Buat koneksi ke database MySQL."""
    return mysql.connector.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME
    )


def ensure_notifications_table():
    """Buat tabel notifications jika belum ada."""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
        CREATE TABLE IF NOT EXISTS notifications (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            farmer_id    INT,
            zone_id      VARCHAR(50),
            type         VARCHAR(50),
            message      TEXT,
            is_read      TINYINT(1) DEFAULT 0,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_farmer (farmer_id),
            INDEX idx_zone (zone_id),
            INDEX idx_read (is_read)
        )
        """)
        conn.commit()
        cursor.close()
        conn.close()
        logger.info("✓ Tabel notifications siap")
    except Exception as e:
        logger.error(f"Error create table: {e}")


def save_notification(farmer_id, zone_id, notif_type, message):
    """Simpan notifikasi ke database."""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
        INSERT INTO notifications(farmer_id, zone_id, type, message)
        VALUES (%s, %s, %s, %s)
        """, (farmer_id, zone_id, notif_type, message))
        conn.commit()
        cursor.close()
        conn.close()
        logger.info(f"💾 Notifikasi tersimpan → farmer_id={farmer_id}, type={notif_type}")
    except Exception as e:
        logger.error(f"Error save_notification: {e}")


def get_farmers_by_zone(zone_id):
    """Ambil semua petani di zona tertentu dari database."""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("""
        SELECT f.id, f.name, f.phone 
        FROM frm_farmers f
        INNER JOIN frm_lands l ON f.id = l.farmer_id
        WHERE l.zone_id = %s AND f.role = 'petani'
        GROUP BY f.id
        """, (zone_id,))
        farmers = cursor.fetchall()
        cursor.close()
        conn.close()
        return farmers
    except Exception as e:
        logger.error(f"Error get_farmers_by_zone: {e}")
        return []


# ── Callback per event ─────────────────────────────────────────

def on_harvest_ready(ch, method, properties, body):
    """
    Event: harvest.ready
    Dipicu ketika ML model memprediksi panen dalam 7 hari ke depan.
    """
    try:
        event = json.loads(body)
        zone_id = event.get('zone')
        predicted_yield = event.get('predicted_yield_ton', 0)
        days_to_harvest = event.get('estimated_harvest_days', 7)
        
        logger.info(f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
        logger.info(f"📬 EVENT RECEIVED: harvest.ready")
        logger.info(f"   Zone: {zone_id}")
        logger.info(f"   Predicted yield: {predicted_yield} ton/hektar")
        logger.info(f"   Days to harvest: {days_to_harvest}")
        
        farmers = get_farmers_by_zone(zone_id)
        logger.info(f"   Farmers in zone: {len(farmers)}")
        
        for farmer in farmers:
            message = (
                f"🌾 NOTIFIKASI PANEN\n\n"
                f"Petani: {farmer['name']}\n"
                f"Zona: {zone_id}\n"
                f"Status: Prediksi panen dalam {days_to_harvest} hari\n"
                f"Estimasi hasil: {predicted_yield:.1f} ton/hektar\n"
                f"Rekomendasi: Hubungi petugas dinas untuk konfirmasi panen\n"
                f"Waktu notifikasi: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}"
            )
            save_notification(farmer['id'], zone_id, 'harvest_ready', message)
            logger.info(f"   → Notification for {farmer['name']} (farmer_id={farmer['id']}) saved")
        
        logger.info(f"✓ Event harvest.ready processed successfully")
        logger.info(f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n")
        
        ch.basic_ack(delivery_tag=method.delivery_tag)
    except Exception as e:
        logger.error(f"✗ Error processing harvest.ready: {e}")
        ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)


def on_alert_pest(ch, method, properties, body):
    """
    Event: alert.pest
    Dipicu ketika ML model mendeteksi gejala hama/penyakit di zona tertentu.
    """
    try:
        event = json.loads(body)
        zone_id = event.get('zone')
        pest_type = event.get('pest_type', 'tidak diketahui')
        severity = event.get('severity', 'sedang')
        confidence = event.get('confidence', 0)
        affected_plants = event.get('affected_plants', 'tidak diketahui')
        
        # Emoji untuk severity
        severity_emoji = {
            'rendah': '🟡',
            'sedang': '🟠',
            'tinggi': '🔴',
            'kritis': '🚨'
        }
        emoji = severity_emoji.get(severity, '⚠️')
        
        logger.info(f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
        logger.info(f"{emoji} EVENT RECEIVED: alert.pest")
        logger.info(f"   Zone: {zone_id}")
        logger.info(f"   Pest type: {pest_type}")
        logger.info(f"   Severity: {severity} (confidence: {confidence:.1%})")
        logger.info(f"   Affected plants: {affected_plants}")
        
        farmers = get_farmers_by_zone(zone_id)
        logger.info(f"   Farmers to notify: {len(farmers)}")
        
        for farmer in farmers:
            message = (
                f"{emoji} PERINGATAN HAMA/PENYAKIT\n\n"
                f"Petani: {farmer['name']}\n"
                f"Zona: {zone_id}\n"
                f"Jenis: {pest_type}\n"
                f"Tingkat keparahan: {severity.upper()}\n"
                f"Akurasi deteksi: {confidence:.1%}\n"
                f"Tanaman terpengaruh: {affected_plants}\n"
                f"Rekomendasi: SEGERA hubungi petugas dinas pertanian!\n"
                f"Waktu alert: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}"
            )
            save_notification(farmer['id'], zone_id, 'pest_alert', message)
            logger.info(f"   → Alert for {farmer['name']} (farmer_id={farmer['id']}) saved")
        
        logger.info(f"✓ Event alert.pest processed successfully")
        logger.info(f"━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n")
        
        ch.basic_ack(delivery_tag=method.delivery_tag)
    except Exception as e:
        logger.error(f"✗ Error processing alert.pest: {e}")
        ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)


# ── Main ───────────────────────────────────────────────────────

def main():
    logger.info("=" * 60)
    logger.info("🤖 NOTIFICATION WORKER STARTING")
    logger.info("=" * 60)
    logger.info(f"RabbitMQ: {RABBITMQ_HOST}:{RABBITMQ_PORT}")
    logger.info(f"Database: {DB_HOST}:{DB_PORT}/{DB_NAME}")
    
    ensure_notifications_table()
    
    # Setup RabbitMQ connection dengan retry
    credentials = pika.PlainCredentials(RABBITMQ_USER, RABBITMQ_PASS)
    params = pika.ConnectionParameters(
        host=RABBITMQ_HOST,
        port=RABBITMQ_PORT,
        credentials=credentials,
        heartbeat=60,
        blocked_connection_timeout=300
    )
    
    logger.info("\n📡 Connecting to RabbitMQ...")
    for attempt in range(1, 11):
        try:
            connection = pika.BlockingConnection(params)
            logger.info("✓ Connected to RabbitMQ")
            break
        except Exception as e:
            logger.warning(f"   Attempt {attempt}/10 failed: {e}")
            if attempt < 10:
                logger.info(f"   Retrying in 5 seconds...")
                time.sleep(5)
            else:
                logger.error("✗ Failed to connect to RabbitMQ after 10 attempts")
                return
    
    channel = connection.channel()
    
    # Declare queues
    logger.info("\n📦 Declaring queues...")
    channel.queue_declare(queue='harvest.ready', durable=True)
    channel.queue_declare(queue='alert.pest', durable=True)
    logger.info("✓ Queues declared")
    
    # Set prefetch count
    channel.basic_qos(prefetch_count=1)
    
    # Register consumers
    logger.info("\n👂 Registering consumers...")
    channel.basic_consume('harvest.ready', on_harvest_ready)
    channel.basic_consume('alert.pest', on_alert_pest)
    logger.info("✓ Consumers registered")
    
    logger.info("\n" + "=" * 60)
    logger.info("✓ WORKER READY - Waiting for events...")
    logger.info("=" * 60)
    logger.info("  Queue 1: harvest.ready  → Notification panen")
    logger.info("  Queue 2: alert.pest     → Peringatan hama/penyakit")
    logger.info("\nPress CTRL+C to stop\n")
    
    try:
        channel.start_consuming()
    except KeyboardInterrupt:
        logger.info("\n⏹️  STOPPING WORKER...")
        channel.stop_consuming()
        logger.info("✓ Worker stopped")
    finally:
        connection.close()
        logger.info("✓ RabbitMQ connection closed")
        logger.info("=" * 60)


if __name__ == '__main__':
    main()

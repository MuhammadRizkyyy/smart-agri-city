#!/usr/bin/env php
<?php
/**
 * RabbitMQ Consumer — alert.pest
 *
 * Mendengarkan event alert.pest dari Python ML Service.
 * Ketika hama/penyakit terdeteksi:
 *   1. Parse payload dari Python ML sensor_consumer.py
 *   2. Simpan alert ke crp_alerts di DB
 *   3. Acknowledge pesan
 *
 * Dijalankan otomatis via supervisord di Dockerfile php-crop.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Support EnvLoader jika tersedia, fallback ke getenv()
if (class_exists('\App\Services\EnvLoader')) {
    \App\Services\EnvLoader::load(dirname(__DIR__));
}

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Models\Alert;

$host = getenv('RABBITMQ_HOST')     ?: '127.0.0.1';
$port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
$user = getenv('RABBITMQ_USERNAME') ?: getenv('RABBITMQ_USER') ?: 'guest';
$pass = getenv('RABBITMQ_PASSWORD') ?: getenv('RABBITMQ_PASS') ?: 'guest';

const EXCHANGE   = 'agri.events';
const QUEUE_NAME = 'alert.pest';

echo "[Consumer:alert.pest] Starting consumer...\n";

// Retry loop: reconnect jika RabbitMQ belum siap
$maxRetries = 10;
$retryDelay = 5;
$attempt    = 0;
$connection = null;

while ($attempt < $maxRetries) {
    try {
        $connection = new AMQPStreamConnection($host, $port, $user, $pass);
        echo "[Consumer:alert.pest] Connected to RabbitMQ at {$host}:{$port}\n";
        break;
    } catch (\Exception $e) {
        $attempt++;
        echo "[Consumer:alert.pest] Connection failed (attempt {$attempt}/{$maxRetries}): {$e->getMessage()}\n";
        if ($attempt >= $maxRetries) {
            echo "[Consumer:alert.pest] Max retries reached. Exiting.\n";
            exit(1);
        }
        sleep($retryDelay);
    }
}

$channel = $connection->channel();

// WAJIB: Declare exchange terlebih dahulu (topic, durable)
// Python ML publish ke exchange ini dengan routing_key='alert.pest'
$channel->exchange_declare(EXCHANGE, 'topic', false, true, false);

// Declare queue durable agar pesan tidak hilang saat restart
$channel->queue_declare(QUEUE_NAME, false, true, false, false);

// Bind queue ke exchange dengan routing key yang sama
$channel->queue_bind(QUEUE_NAME, EXCHANGE, QUEUE_NAME);

echo "[Consumer:alert.pest] Exchange declared, queue bound. Listening on '" . QUEUE_NAME . "'...\n";

/**
 * Map severity dari Python ML ('WARNING', 'CRITICAL', dll.)
 * ke nilai yang digunakan skema DB crp_alerts (rendah/sedang/tinggi/kritis)
 */
function mapSeverity(string $severity): string {
    $map = [
        'WARNING'  => 'tinggi',
        'CRITICAL' => 'kritis',
        'HIGH'     => 'tinggi',
        'MEDIUM'   => 'sedang',
        'LOW'      => 'rendah',
        'rendah'   => 'rendah',
        'sedang'   => 'sedang',
        'tinggi'   => 'tinggi',
        'kritis'   => 'kritis',
    ];
    return $map[strtoupper($severity)] ?? $map[$severity] ?? 'sedang';
}

/**
 * Resolve zone_id: Python ML mengirim nilai zone berupa nama string
 * (misal "1", "zone-a", "Zone-C") atau integer.
 */
function resolveZoneId($zone): int {
    if (is_numeric($zone)) {
        return (int) $zone;
    }
    // Mapping nama zona simulator → zone_id sesuai seed.sql
    $mapping = [
        'zone-a' => 1, 'zone-b' => 2, 'zone-c' => 3,
        'zone-d' => 4, 'zone-e' => 5,
        'zona1'  => 1, 'zona2'  => 2, 'zona3'  => 3, 'zona4' => 4,
        'zone-a' => 1,
    ];
    $key = strtolower(trim((string) $zone));
    return $mapping[$key] ?? 1; // default ke zone 1 jika tidak ditemukan
}

$callback = function (AMQPMessage $msg) {
    echo "[Consumer:alert.pest] Received message: " . $msg->body . "\n";
    $data = json_decode($msg->body, true);

    if (!is_array($data)) {
        echo "[Consumer:alert.pest] Invalid JSON payload, discarding.\n";
        $msg->ack();
        return;
    }

    // Python ML sensor_consumer.py mengirim field:
    // zone, zone_id, threat_detected, severity, timestamp
    $rawZone       = $data['zone_id'] ?? $data['zone'] ?? 1;
    $threatLabel   = $data['threat_detected'] ?? $data['alert_type'] ?? 'pest';
    $rawSeverity   = $data['severity']        ?? 'WARNING';
    $description   = $data['description']     ??
                     "Hama '{$threatLabel}' terdeteksi oleh ML di zona {$rawZone}. Waktu: " .
                     ($data['timestamp'] ?? date('Y-m-d H:i:s'));

    $zoneId   = resolveZoneId($rawZone);
    $severity = mapSeverity($rawSeverity);

    $alertData = [
        'zone_id'     => $zoneId,
        'alert_type'  => $threatLabel,
        'severity'    => $severity,
        'description' => $description,
    ];

    try {
        $alertModel = new Alert();
        $record     = $alertModel->create($alertData);
        echo "[Consumer:alert.pest] Alert saved to DB: id={$record['id']}, zone={$zoneId}, threat={$threatLabel}, severity={$severity}\n";
        $msg->ack();
    } catch (\Exception $e) {
        echo "[Consumer:alert.pest] Failed to save alert to DB: " . $e->getMessage() . "\n";
        // Nack tanpa requeue untuk mencegah infinite loop
        $msg->nack(false);
    }
};

$channel->basic_qos(null, 1, null);
$channel->basic_consume(QUEUE_NAME, '', false, false, false, false, $callback);

echo "[Consumer:alert.pest] Waiting for pest alert events. Press CTRL+C to stop.\n";

try {
    while ($channel->is_consuming()) {
        $channel->wait();
    }
} catch (\Throwable $e) {
    echo "[Consumer:alert.pest] Fatal error: " . $e->getMessage() . "\n";
} finally {
    $channel->close();
    $connection->close();
    echo "[Consumer:alert.pest] Consumer stopped.\n";
}

#!/usr/bin/env php
<?php
/**
 * RabbitMQ Consumer — irrigation.trigger
 *
 * Mendengarkan event irrigation.trigger dari Python ML Service.
 * Ketika moisture kritis terdeteksi ML:
 *   1. Buat log irigasi di DB (trigger_type = otomatis_ml)
 *   2. Publish event iot.valve ke RabbitMQ (dikonsumsi Node-RED → buka valve MQTT)
 *
 * Jalankan: php bin/consumer.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
\App\Services\EnvLoader::load(dirname(__DIR__));

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Models\Database;
use App\Models\IrrigationLog;
use App\Models\Zone;
use App\Services\RabbitMQPublisher;

$host = getenv('RABBITMQ_HOST')     ?: 'rabbitmq';
$port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
$user = getenv('RABBITMQ_USERNAME') ?: getenv('RABBITMQ_USER') ?: 'guest';
$pass = getenv('RABBITMQ_PASSWORD') ?: getenv('RABBITMQ_PASS') ?: 'guest';

const EXCHANGE      = 'agri.events';
const QUEUE_CONSUME = 'irrigation.trigger';
const QUEUE_VALVE   = 'iot.valve';

echo "[Consumer] Irrigation trigger consumer starting...\n";

// Retry loop: reconnect jika RabbitMQ belum siap
$maxRetries  = 10;
$retryDelay  = 5; // detik
$attempt     = 0;
$connection  = null;

while ($attempt < $maxRetries) {
    try {
        $connection = new AMQPStreamConnection($host, $port, $user, $pass);
        echo "[Consumer] Connected to RabbitMQ at {$host}:{$port}\n";
        break;
    } catch (\Exception $e) {
        $attempt++;
        echo "[Consumer] Connection failed (attempt {$attempt}/{$maxRetries}): {$e->getMessage()}\n";
        if ($attempt >= $maxRetries) {
            echo "[Consumer] Max retries reached. Exiting.\n";
            exit(1);
        }
        sleep($retryDelay);
    }
}

$channel = $connection->channel();

// Declare exchange & queue, bind routing key
$channel->exchange_declare(EXCHANGE, 'topic', false, true, false);
$channel->queue_declare(QUEUE_CONSUME, false, true, false, false);
$channel->queue_bind(QUEUE_CONSUME, EXCHANGE, QUEUE_CONSUME);

$channel->queue_declare(QUEUE_VALVE, false, true, false, false);
$channel->queue_bind(QUEUE_VALVE, EXCHANGE, QUEUE_VALVE);

echo "[Consumer] Listening on queue: " . QUEUE_CONSUME . "\n";

/**
 * Handler untuk setiap pesan irrigation.trigger
 */
$callback = function (AMQPMessage $msg) use ($channel) {
    $payload = json_decode($msg->body, true);
    if (!$payload) {
        echo "[Consumer] Invalid JSON payload, discarding.\n";
        $channel->basic_ack($msg->getDeliveryTag());
        return;
    }

    $zone         = $payload['zone']         ?? $payload['zone_id'] ?? null;
    $soilMoisture = $payload['soil_moisture'] ?? $payload['moisture'] ?? null;
    $urgency      = $payload['urgency']       ?? 'HIGH';
    $action       = strtolower($payload['action'] ?? 'start');  // start atau stop
    $triggerType  = $payload['trigger_type']   ?? 'manual';

    echo "[Consumer] Received irrigation.trigger — zone: {$zone}, action: {$action}, trigger: {$triggerType}\n";

    try {
        // Resolve zone_id: bisa berupa nama string (zona1, Zone-A) atau integer
        $zoneModel = new Zone();
        $zoneId    = null;

        if (is_numeric($zone)) {
            $zoneId = (int)$zone;
        } else {
            // Cari zone berdasarkan nama (case-insensitive partial match)
            $zones = $zoneModel->getAll();
            foreach ($zones as $z) {
                if (stripos($z['name'], (string)$zone) !== false ||
                    strtolower($z['name']) === strtolower((string)$zone)) {
                    $zoneId = (int)$z['id'];
                    break;
                }
            }
            // Fallback: pakai zona pertama jika tidak ditemukan
            if (!$zoneId && !empty($zones)) {
                $zoneId = (int)$zones[0]['id'];
                echo "[Consumer] Warning: zone '{$zone}' not found, defaulting to zone_id={$zoneId}\n";
            }
        }

        if (!$zoneId) {
            echo "[Consumer] No valid zone found, discarding message.\n";
            $channel->basic_ack($msg->getDeliveryTag());
            return;
        }

        $logModel = new IrrigationLog();

        // HANDLE START ACTION
        if ($action === 'start') {
            // Cek apakah sudah ada sesi irigasi aktif di zona ini
            $activeLog = $logModel->findActiveLog($zoneId);

            if ($activeLog) {
                echo "[Consumer] Zone {$zoneId} already has active irrigation (log_id={$activeLog['id']}), skipping.\n";
                $channel->basic_ack($msg->getDeliveryTag());
                return;
            }

            // Buat log irigasi baru
            $newLog = $logModel->startIrrigation($zoneId, $triggerType);
            echo "[Consumer] ✅ Irrigation STARTED for zone {$zoneId}, log_id={$newLog['id']}, trigger={$triggerType}\n";
            $logId = $newLog['id'];
        }
        // HANDLE STOP ACTION
        else if ($action === 'stop') {
            // Tutup irigasi aktif di zona ini
            $activeLog = $logModel->findActiveLog($zoneId);

            if (!$activeLog) {
                echo "[Consumer] Zone {$zoneId} has no active irrigation to stop.\n";
                $channel->basic_ack($msg->getDeliveryTag());
                return;
            }

            // Update log: set ended_at = now
            $logModel->stopIrrigation($zoneId, 0.0);  // volume_liters = 0 (dapat diupdate nanti)
            echo "[Consumer] ✅ Irrigation STOPPED for zone {$zoneId}, log_id={$activeLog['id']}\n";
            $logId = $activeLog['id'];
        } else {
            echo "[Consumer] Unknown action: {$action}\n";
            $channel->basic_ack($msg->getDeliveryTag());
            return;
        }

        // Publish iot.valve ke RabbitMQ untuk Node-RED
        // (Node-RED akan subscribe ke iot.valve dan kontrol MQTT valve)
        $publisher = new RabbitMQPublisher();
        $publisher->publish('iot.valve', [
            'zone_id'      => $zoneId,
            'zone'         => $zone,
            'action'       => $action === 'start' ? 'open' : 'close',  // open atau close valve
            'trigger'      => $triggerType,
            'log_id'       => $logId,
            'soil_moisture'=> $soilMoisture,
            'urgency'      => $urgency,
            'timestamp'    => date('c'),
        ]);

        echo "[Consumer] Published iot.valve for zone {$zoneId} ({$action}) — command sent to Node-RED\n";

        $channel->basic_ack($msg->getDeliveryTag());

    } catch (\Exception $e) {
        echo "[Consumer] Error processing message: {$e->getMessage()}\n";
        error_log("[Consumer] Error: " . $e->getMessage());
        // Nack dengan requeue=false agar tidak loop
        $channel->basic_nack($msg->getDeliveryTag(), false, false);
    }
};

// Prefetch 1 message at a time (fair dispatch)
$channel->basic_qos(null, 1, null);
$channel->basic_consume(QUEUE_CONSUME, '', false, false, false, false, $callback);

echo "[Consumer] Waiting for irrigation trigger events. Press CTRL+C to stop.\n";

try {
    while ($channel->is_consuming()) {
        $channel->wait();
    }
} catch (\Throwable $e) {
    echo "[Consumer] Fatal error: {$e->getMessage()}\n";
} finally {
    $channel->close();
    $connection->close();
    echo "[Consumer] Consumer stopped.\n";
}

#!/usr/bin/env php
<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (class_exists('\App\Services\EnvLoader')) {
    \App\Services\EnvLoader::load(dirname(__DIR__));
}

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Models\Harvest;
use App\Services\Database;

$host = getenv('RABBITMQ_HOST')     ?: '127.0.0.1';
$port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
$user = getenv('RABBITMQ_USERNAME') ?: getenv('RABBITMQ_USER') ?: 'guest';
$pass = getenv('RABBITMQ_PASSWORD') ?: getenv('RABBITMQ_PASS') ?: 'guest';

const EXCHANGE   = 'agri.events';
const QUEUE_NAME = 'harvest.ready';

echo "[Consumer:harvest.ready] Starting consumer...\n";

$maxRetries = 10;
$retryDelay = 5;
$attempt    = 0;
$connection = null;

while ($attempt < $maxRetries) {
    try {
        $connection = new AMQPStreamConnection($host, $port, $user, $pass);
        echo "[Consumer:harvest.ready] Connected to RabbitMQ at {$host}:{$port}\n";
        break;
    } catch (\Exception $e) {
        $attempt++;
        echo "[Consumer:harvest.ready] Connection failed (attempt {$attempt}/{$maxRetries}): {$e->getMessage()}\n";
        if ($attempt >= $maxRetries) {
            echo "[Consumer:harvest.ready] Max retries reached. Exiting.\n";
            exit(1);
        }
        sleep($retryDelay);
    }
}

$channel = $connection->channel();

$channel->exchange_declare(EXCHANGE, 'topic', false, true, false);

$channel->queue_declare(QUEUE_NAME, false, true, false, false);

$channel->queue_bind(QUEUE_NAME, EXCHANGE, QUEUE_NAME);

echo "[Consumer:harvest.ready] Exchange declared, queue bound. Listening on '" . QUEUE_NAME . "'...\n";

function ensureNotificationTable() {
    try {
        $db = Database::connect();
        $stmt = $db->prepare("
            CREATE TABLE IF NOT EXISTS frm_harvest_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                zone_id INT NOT NULL,
                predicted_yield_ton FLOAT NOT NULL,
                predicted_yield_category VARCHAR(20),
                estimated_harvest_days INT,
                crop_type VARCHAR(100),
                soil_condition TEXT,
                recommendation TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_at TIMESTAMP NULL,
                INDEX idx_zone (zone_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $stmt->execute();
        echo "[Consumer:harvest.ready] Notification table ensured.\n";
    } catch (\Exception $e) {
        echo "[Consumer:harvest.ready] Warning: Could not ensure table: " . $e->getMessage() . "\n";
    }
}

// Save harvest notification to database
function saveHarvestNotification($data) {
    try {
        $db = Database::connect();
        
        $stmt = $db->prepare("
            INSERT INTO frm_harvest_notifications
            (zone_id, predicted_yield_ton, predicted_yield_category, 
             estimated_harvest_days, crop_type, soil_condition, recommendation)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['zone_id'] ?? 1,
            $data['predicted_yield_ton'] ?? 0,
            $data['yield_category'] ?? 'Unknown',
            $data['estimated_harvest_days'] ?? 90,
            $data['crop_type'] ?? 'Padi',
            $data['soil_condition'] ?? null,
            $data['recommendation'] ?? null
        ]);
        
        $id = $db->lastInsertId();
        return $id;
    } catch (\Exception $e) {
        echo "[Consumer:harvest.ready] Error saving notification: " . $e->getMessage() . "\n";
        throw $e;
    }
}

ensureNotificationTable();

// Handler untuk setiap pesan harvest.ready
$callback = function (AMQPMessage $msg) {
    $payload = json_decode($msg->body, true);
    if (!$payload) {
        echo "[Consumer:harvest.ready] Invalid JSON payload, discarding.\n";
        $msg->ack();
        return;
    }

    $zone        = $payload['zone']           ?? $payload['zone_id'] ?? null;
    $yieldTon    = $payload['predicted_yield_ton'] ?? $payload['yield_ton'] ?? null;
    $category    = $payload['yield_category']      ?? 'Normal';
    $harvestDays = $payload['estimated_harvest_days'] ?? 90;
    $cropType    = $payload['crop_type'] ?? 'Padi';
    $soilCond    = $payload['soil_condition'] ?? null;
    $recommend   = $payload['recommendation'] ?? 'Siap dipanen dalam ' . $harvestDays . ' hari';

    echo "[Consumer:harvest.ready] Received harvest prediction — zone: {$zone}, yield: {$yieldTon} ton/ha, category: {$category}\n";

    try {
        $id = saveHarvestNotification([
            'zone_id' => $zone,
            'predicted_yield_ton' => $yieldTon,
            'yield_category' => $category,
            'estimated_harvest_days' => $harvestDays,
            'crop_type' => $cropType,
            'soil_condition' => $soilCond,
            'recommendation' => $recommend
        ]);
        
        echo "[Consumer:harvest.ready] Harvest notification saved to DB: id={$id}, zone={$zone}, yield={$yieldTon} ton/ha\n";
        
        $msg->ack();
        
    } catch (\Exception $e) {
        echo "[Consumer:harvest.ready] Failed to process harvest notification: " . $e->getMessage() . "\n";
        $msg->nack(false);
    }
};

$channel->basic_qos(null, 1, null);
$channel->basic_consume(QUEUE_NAME, '', false, false, false, false, $callback);

echo "[Consumer:harvest.ready] Waiting for harvest prediction events. Press CTRL+C to stop.\n";

try {
    while ($channel->is_consuming()) {
        $channel->wait();
    }
} catch (\Throwable $e) {
    echo "[Consumer:harvest.ready] Fatal error: " . $e->getMessage() . "\n";
} finally {
    $channel->close();
    $connection->close();
    echo "[Consumer:harvest.ready] Consumer stopped.\n";
}

#!/usr/bin/env php
<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
\App\Services\EnvLoader::load(dirname(__DIR__));

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$host = getenv('RABBITMQ_HOST')     ?: 'rabbitmq';
$port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
$user = getenv('RABBITMQ_USERNAME') ?: getenv('RABBITMQ_USER') ?: 'guest';
$pass = getenv('RABBITMQ_PASSWORD') ?: getenv('RABBITMQ_PASS') ?: 'guest';

const EXCHANGE = 'agri.events';
const QUEUE_BINDINGS = [
    'sensor.new'          => 'sensor.new',
    'irrigation.trigger'  => 'irrigation.trigger',
    'alert.pest'          => 'alert.pest',
    'harvest.ready'       => 'harvest.ready',
    'iot.valve'           => 'iot.valve',
];

$queueDir = '/var/www/html/queue';
$logFile = '/var/log/async-publisher.log';

function log_message($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $msg\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
    flush();
    ob_flush();
}

log_message("[Async Publisher] Starting worker...");

// Retry loop untuk koneksi RabbitMQ
$maxRetries = 10;
$retryDelay = 5;
$attempt = 0;
$connection = null;

while ($attempt < $maxRetries && !$connection) {
    try {
        $connection = new AMQPStreamConnection($host, $port, $user, $pass);
        log_message("[Async Publisher] Connected to RabbitMQ at {$host}:{$port}");
        break;
    } catch (\Exception $e) {
        $attempt++;
        log_message("[Async Publisher] Connection failed (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
        if ($attempt >= $maxRetries) {
            log_message("[Async Publisher] Max retries reached. Exiting.");
            exit(1);
        }
        sleep($retryDelay);
    }
}

$channel = $connection->channel();

// Declare exchange
$channel->exchange_declare(EXCHANGE, 'topic', false, true, false);

// Declare all queues
foreach (QUEUE_BINDINGS as $routingKey => $queueName) {
    $channel->queue_declare($queueName, false, true, false, false);
    $channel->queue_bind($queueName, EXCHANGE, $routingKey);
}

log_message("[Async Publisher] Ready to process queued messages");

// Main loop: process queued files
log_message("[Async Publisher] Entering main processing loop...");
$loopCount = 0;
while (true) {
    $loopCount++;
    $now = date('Y-m-d H:i:s');
    
    if (!is_dir($queueDir)) {
        if ($loopCount % 10 == 0) {
            log_message("[Async Publisher] [$now] Loop $loopCount: Queue directory not found: $queueDir");
        }
        sleep(1);
        continue;
    }

    $files = glob($queueDir . '/msg_*.json');
    $fileCount = count($files);
    
    if ($loopCount % 60 == 0) {
        log_message("[Async Publisher] [$now] Loop $loopCount: glob() returned $fileCount file(s)");
    }
    
    if (empty($files)) {
        sleep(1);
        continue;
    }

    log_message("[Async Publisher] Found $fileCount file(s) to process at $now");

    foreach ($files as $filename) {
        try {
            $content = file_get_contents($filename);
            $message = json_decode($content, true);

            if (!$message) {
                log_message("[Async Publisher] Invalid JSON in {$filename}, skipping");
                @unlink($filename);
                continue;
            }

            $routingKey = $message['routing_key'] ?? null;
            $data = $message['data'] ?? [];
            $attempts = ($message['attempts'] ?? 0) + 1;

            if (!$routingKey) {
                log_message("[Async Publisher] No routing_key in {$filename}, skipping");
                @unlink($filename);
                continue;
            }

            $queueName = QUEUE_BINDINGS[$routingKey] ?? $routingKey;
            $msg = new AMQPMessage(
                json_encode($data, JSON_UNESCAPED_UNICODE),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            $channel->basic_publish($msg, EXCHANGE, $routingKey);
            log_message("[Async Publisher] Published to {$routingKey} (queue: {$queueName})");

            @unlink($filename);

        } catch (\Exception $e) {
            $attempts = ($message['attempts'] ?? 0) + 1;
            
            if ($attempts >= 3) {
                $errorDir = $queueDir . '/error';
                @mkdir($errorDir, 0777, true);
                $errorFile = $errorDir . '/' . basename($filename) . '.error';
                rename($filename, $errorFile);
                log_message("[Async Publisher] Max retries for {$filename}, moved to error dir");
            } else {
                $message['attempts'] = $attempts;
                file_put_contents($filename, json_encode($message));
                log_message("[Async Publisher] Error processing {$filename} (attempt {$attempts}): " . $e->getMessage());
            }
        }
    }

    sleep(1);
}

$channel->close();
$connection->close();
log_message("[Async Publisher] Worker stopped");

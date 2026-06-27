<?php
namespace App\Services;

class RabbitMQPublisher {
    /**
     * Queue binding map: routing_key → queue_name
     * Pastikan semua consumer (Python ML, PHP services) declare queue yang sama.
     */
    private const QUEUE_BINDINGS = [
        'sensor.new'          => 'sensor.new',
        'irrigation.trigger'  => 'irrigation.trigger',
        'alert.pest'          => 'alert.pest',
        'harvest.ready'       => 'harvest.ready',
        'iot.valve'           => 'iot.valve',
    ];

    public function publish(string $routingKey, array $data): void {
        // Queue message to file for async processing (non-blocking)
        // This prevents HTTP response from hanging on RabbitMQ connection
        $queueDir = dirname(__DIR__, 2) . '/queue';
        if (!is_dir($queueDir)) {
            @mkdir($queueDir, 0777, true);
        }

        $message = [
            'routing_key' => $routingKey,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'attempts' => 0
        ];

        $filename = $queueDir . '/' . uniqid('msg_', true) . '.json';
        $written = file_put_contents($filename, json_encode($message, JSON_UNESCAPED_UNICODE));

        if ($written) {
            error_log("[RabbitMQ] Message queued to file: {$filename} [routing_key: {$routingKey}]");
        } else {
            error_log("[RabbitMQ] Failed to queue message to file: {$filename}");
        }
    }
}

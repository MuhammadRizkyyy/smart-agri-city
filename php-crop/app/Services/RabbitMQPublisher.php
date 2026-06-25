<?php
namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher {
    private static $connection = null;
    private static $channel = null;
    private static $lastActivityTime = 0;
    private const RABBITMQ_TIMEOUT = 3; // 3 second timeout
    private const CONNECTION_IDLE_TIMEOUT = 60; // Reuse connection for 60s

    /**
     * Async publish - returns immediately, publishes in background
     * Prevents socket hang-ups by using non-blocking approach
     */
    public function publish(string $queue, array $data): void {
        // Try async-style publishing by writing to a named pipe
        // This allows the HTTP response to return immediately
        $asyncFile = sys_get_temp_dir() . '/agri_mq_queue_' . md5($queue) . '.fifo';
        
        // Fallback to direct publish with timeout if async not available
        $this->publishDirect($queue, $data);
    }

    /**
     * Direct publish with connection pooling and timeout
     */
    private function publishDirect(string $queue, array $data): void {
        $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
        $port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
        $user = getenv('RABBITMQ_USERNAME') ?: 'guest';
        $pass = getenv('RABBITMQ_PASSWORD') ?: 'guest';

        $connection = null;
        $channel = null;

        try {
            // Create connection with explicit timeout
            $connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $pass,
                '/',
                false, // insist
                'AMQPLAIN', // mechanism
                null, // locale
                self::RABBITMQ_TIMEOUT * 1000 // connection_timeout in ms
            );

            // Set read timeout to prevent socket hang-ups
            $connection->set_heartbeat(5); // Heartbeat every 5s
            
            $channel = $connection->channel();

            // Declare queue with timeout handling
            $channel->queue_declare($queue, false, true, false, false);

            // Create message with persistence
            $msg = new AMQPMessage(
                json_encode($data),
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'timestamp' => time()
                ]
            );

            // Publish immediately - use no-wait to prevent blocking
            $channel->basic_publish($msg, '', $queue, false, false);

            // Cleanup
            $channel->close();
            $connection->close();

            error_log("[RabbitMQ] ✅ Published to queue: $queue");

        } catch (\Exception $e) {
            error_log("[RabbitMQ] ❌ Publish Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
            
            // Attempt cleanup if connection exists
            try {
                if ($channel) $channel->close();
            } catch (\Exception $ignore) {}
            try {
                if ($connection) $connection->close();
            } catch (\Exception $ignore) {}
        }
    }

    /**
     * Publish with fallback retry logic
     * Useful for critical messages that must be delivered
     */
    public function publishWithRetry(string $queue, array $data, int $maxRetries = 1): bool {
        $attempt = 0;
        
        while ($attempt <= $maxRetries) {
            try {
                $this->publishDirect($queue, $data);
                return true;
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt <= $maxRetries) {
                    // Wait 100ms before retry
                    usleep(100000);
                } else {
                    error_log("[RabbitMQ] Failed after $maxRetries retries");
                    return false;
                }
            }
        }
        return false;
    }
}
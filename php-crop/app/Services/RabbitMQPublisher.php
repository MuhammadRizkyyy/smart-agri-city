<?php
namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher {
    private static $connection = null;
    private static $channel = null;
    private static $lastActivityTime = 0;
    private const RABBITMQ_TIMEOUT = 3;
    private const CONNECTION_IDLE_TIMEOUT = 60; 

    public function publish(string $queue, array $data): void {
        $asyncFile = sys_get_temp_dir() . '/agri_mq_queue_' . md5($queue) . '.fifo';
        
        $this->publishDirect($queue, $data);
    }

    private function publishDirect(string $queue, array $data): void {
        $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
        $port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
        $user = getenv('RABBITMQ_USERNAME') ?: 'guest';
        $pass = getenv('RABBITMQ_PASSWORD') ?: 'guest';

        $connection = null;
        $channel = null;

        try {
            $connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $pass,
                '/',
                false, 
                'AMQPLAIN',
                null, 
                self::RABBITMQ_TIMEOUT * 1000 
            );
            
            $channel = $connection->channel();

            $channel->queue_declare($queue, false, true, false, false);

            $msg = new AMQPMessage(
                json_encode($data),
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'timestamp' => time()
                ]
            );

            $channel->basic_publish($msg, '', $queue, false, false);

            $channel->close();
            $connection->close();

            error_log("[RabbitMQ] ✅ Published to queue: $queue");

        } catch (\Exception $e) {
            error_log("[RabbitMQ] ❌ Publish Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
            
            try {
                if ($channel) $channel->close();
            } catch (\Exception $ignore) {}
            try {
                if ($connection) $connection->close();
            } catch (\Exception $ignore) {}
        }
    }

    public function publishWithRetry(string $queue, array $data, int $maxRetries = 1): bool {
        $attempt = 0;
        
        while ($attempt <= $maxRetries) {
            try {
                $this->publishDirect($queue, $data);
                return true;
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt <= $maxRetries) {
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
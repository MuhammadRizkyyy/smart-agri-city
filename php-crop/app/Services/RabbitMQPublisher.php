<?php
namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher {
    public function publish(string $queue, array $data): void {
        $host = getenv('RABBITMQ_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
        $user = getenv('RABBITMQ_USERNAME') ?: 'guest';
        $pass = getenv('RABBITMQ_PASSWORD') ?: 'guest';

        try {
            $connection = new AMQPStreamConnection($host, $port, $user, $pass);
            $channel = $connection->channel();

            $channel->queue_declare($queue, false, true, false, false);

            $msg = new AMQPMessage(
                json_encode($data),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            $channel->basic_publish($msg, '', $queue);

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            error_log("RabbitMQ Publish Error: " . $e->getMessage());
        }
    }
}
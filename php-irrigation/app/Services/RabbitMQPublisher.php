<?php
namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher {
    public function publish(string $routingKey, array $data): void {
        $host = getenv('RABBITMQ_HOST') ?: '127.0.0.1';
        $port = getenv('RABBITMQ_PORT') ?: 5672;
        $user = getenv('RABBITMQ_USER') ?: getenv('RABBITMQ_USERNAME') ?: 'guest';
        $pass = getenv('RABBITMQ_PASS') ?: getenv('RABBITMQ_PASSWORD') ?: 'guest';
        $exchange = 'agri.events';

        try {
            $connection = new AMQPStreamConnection($host, $port, $user, $pass);
            $channel = $connection->channel();

            // Declare topic exchange
            $channel->exchange_declare($exchange, 'topic', false, true, false);

            $msg = new AMQPMessage(
                json_encode($data),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            $channel->basic_publish($msg, $exchange, $routingKey);

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            error_log("RabbitMQ Publish Error: " . $e->getMessage());
        }
    }
}
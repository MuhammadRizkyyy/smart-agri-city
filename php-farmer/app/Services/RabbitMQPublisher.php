<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private $channel;
    private $connection;

    public function __construct()
    {
        $host     = getenv('RABBITMQ_HOST')     ?: 'localhost';
        $port     = (int)(getenv('RABBITMQ_PORT')     ?: 5672);
        $user     = getenv('RABBITMQ_USERNAME') ?: 'guest';
        $password = getenv('RABBITMQ_PASSWORD') ?: 'guest';

        $this->connection = new AMQPStreamConnection(
            $host,
            $port,
            $user,
            $password
        );

        $this->channel = $this->connection->channel();
    }

    public function publish(string $queue, array $data): void
    {
        $this->channel->queue_declare(
            $queue,
            false, 
            true,  
            false, 
            false  
        );

        $message = new AMQPMessage(
            json_encode($data),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );

        $this->channel->basic_publish($message, '', $queue);
    }

    public function __destruct()
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (\Throwable $e) {
        }
    }
}

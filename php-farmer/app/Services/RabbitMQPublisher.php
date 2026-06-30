<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private static $instance = null;
    private $channel;
    private $connection;
    private $isConnected = false;

    private function __construct()
    {
        $this->connect();
    }

    /**
     * Get singleton instance (connection pooling)
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish connection with timeout and error handling
     */
    private function connect(): void
    {
        try {
            $host     = getenv('RABBITMQ_HOST')     ?: 'localhost';
            $port     = (int)(getenv('RABBITMQ_PORT')     ?: 5672);
            $user     = getenv('RABBITMQ_USERNAME') ?: 'guest';
            $password = getenv('RABBITMQ_PASSWORD') ?: 'guest';

            // Set connection timeout to 3 seconds
            $this->connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                '/',
                false, // insist
                'AMQPLAIN',
                null,
                'en_US',
                3,     // connection_timeout
                3      // read_write_timeout
            );

            $this->channel = $this->connection->channel();
            $this->isConnected = true;

            error_log('[RabbitMQ] Connection established successfully');
        } catch (\Throwable $e) {
            error_log('[RabbitMQ] Connection failed: ' . $e->getMessage());
            $this->isConnected = false;
            throw $e;
        }
    }

    /**
     * Publish message asynchronously (non-blocking)
     */
    public function publish(string $queue, array $data): void
    {
        // If connection is down, try to reconnect once
        if (!$this->isConnected) {
            try {
                $this->reconnect();
            } catch (\Throwable $e) {
                error_log('[RabbitMQ] Reconnection failed, skipping publish: ' . $e->getMessage());
                return;
            }
        }

        try {
            // Declare queue with durable flag
            $this->channel->queue_declare(
                $queue,
                false, // passive
                true,  // durable
                false, // exclusive
                false  // auto_delete
            );

            // Create persistent message
            $message = new AMQPMessage(
                json_encode($data),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            // Publish non-blocking
            $this->channel->basic_publish($message, '', $queue);

            // Flush to ensure delivery
            $this->channel->wait_for_basic_nack(0.1);

            error_log('[RabbitMQ] Message published to queue: ' . $queue);
        } catch (\Throwable $e) {
            error_log('[RabbitMQ] Publish failed for queue ' . $queue . ': ' . $e->getMessage());
            $this->isConnected = false;
            // Don't throw - allow request to succeed even if RabbitMQ fails
        }
    }

    /**
     * Reconnect if connection is lost
     */
    private function reconnect(): void
    {
        try {
            // Close existing connection
            if ($this->channel) {
                try {
                    $this->channel->close();
                } catch (\Throwable $e) {
                }
            }
            if ($this->connection) {
                try {
                    $this->connection->close();
                } catch (\Throwable $e) {
                }
            }

            // Establish new connection
            $this->connect();
        } catch (\Throwable $e) {
            error_log('[RabbitMQ] Reconnection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Close connection gracefully
     */
    public function close(): void
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
            $this->isConnected = false;
            error_log('[RabbitMQ] Connection closed');
        } catch (\Throwable $e) {
            error_log('[RabbitMQ] Error closing connection: ' . $e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}

<?php
namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher {
    private const EXCHANGE = 'agri.events';

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
        // TEMPORARY: Skip RabbitMQ publishing to debug gateway timeout issue
        error_log("[RabbitMQ] Publishing disabled temporarily for debugging");
        return;
        
        $host     = getenv('RABBITMQ_HOST')     ?: 'rabbitmq';
        $port     = (int)(getenv('RABBITMQ_PORT') ?: 5672);
        $user     = getenv('RABBITMQ_USERNAME') ?: getenv('RABBITMQ_USER') ?: 'guest';
        $pass     = getenv('RABBITMQ_PASSWORD') ?: getenv('RABBITMQ_PASS') ?: 'guest';

        try {
            // Add connection timeout to prevent hanging (default: 3 seconds should be OK)
            $connection = new AMQPStreamConnection(
                $host, $port, $user, $pass,
                '/',           // vhost
                false,         // insist
                'AMQPLAIN',    // login_method
                null,          // login_response
                'en_US',       // locale
                5              // connection_timeout (increased to 5 seconds)
            );
            $channel    = $connection->channel();

            // Declare topic exchange (durable, tidak auto-delete)
            $channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);

            // Declare dan bind queue ke exchange agar pesan routing berjalan
            $queueName = self::QUEUE_BINDINGS[$routingKey] ?? $routingKey;
            $channel->queue_declare($queueName, false, true, false, false);
            $channel->queue_bind($queueName, self::EXCHANGE, $routingKey);

            $msg = new AMQPMessage(
                json_encode($data, JSON_UNESCAPED_UNICODE),
                ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
            );

            $channel->basic_publish($msg, self::EXCHANGE, $routingKey);

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            // Log error tapi jangan lempar exception — agar HTTP response tetap sukses
            error_log("[RabbitMQ] Publish error [{$routingKey}]: " . $e->getMessage());
        }
    }
}

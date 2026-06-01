<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    private $channel;

    public function __construct()
    {
        $connection = new AMQPStreamConnection(
            'localhost', // host
            5672,        // port
            'guest',     // user
            'guest'      // password
        );

        $this->channel = $connection->channel();
    }

    public function publish($queue, $data)
    {
        $this->channel->queue_declare(
            $queue,
            false,
            true,
            false,
            false
        );

        $message = new AMQPMessage(
            json_encode($data)
        );

        $this->channel->basic_publish(
            $message,
            '',
            $queue
        );
    }
}
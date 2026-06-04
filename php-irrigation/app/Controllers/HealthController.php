<?php
namespace App\Controllers;

use App\Models\Database;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class HealthController extends BaseController {
    public function check(): void {
        $dbStatus  = 'disconnected';
        $mqStatus  = 'disconnected';
        $dbError   = null;
        $mqError   = null;

        // Check Database
        try {
            $db   = new Database();
            $conn = $db->getConnection();
            $conn->query("SELECT 1")->execute();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbError = $e->getMessage();
        }

        // Check RabbitMQ
        try {
            $host = getenv('RABBITMQ_HOST')     ?: '127.0.0.1';
            $port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
            $user = getenv('RABBITMQ_USERNAME') ?: getenv('RABBITMQ_USER') ?: 'guest';
            $pass = getenv('RABBITMQ_PASSWORD') ?: getenv('RABBITMQ_PASS') ?: 'guest';

            $connection = new AMQPStreamConnection($host, $port, $user, $pass);
            $connection->close();
            $mqStatus = 'connected';
        } catch (\Exception $e) {
            $mqError = $e->getMessage();
        }

        $allHealthy = ($dbStatus === 'connected' && $mqStatus === 'connected');
        $httpCode   = $allHealthy ? 200 : 503;

        $payload = [
            'service'  => 'irrigation-service',
            'status'   => $allHealthy ? 'healthy' : 'unhealthy',
            'checks'   => [
                'database' => [
                    'status' => $dbStatus,
                    'error'  => $dbError,
                ],
                'rabbitmq' => [
                    'status' => $mqStatus,
                    'error'  => $mqError,
                ],
            ],
        ];

        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpCode);
        echo json_encode([
            'status'    => $allHealthy ? 'success' : 'error',
            'code'      => $httpCode,
            'data'      => $payload,
            'message'   => $allHealthy ? 'All systems healthy' : 'One or more systems unhealthy',
            'timestamp' => date('c'),
            'service'   => 'irrigation-service',
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}

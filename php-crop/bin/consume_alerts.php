<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use App\Models\Alert;

$host = getenv('RABBITMQ_HOST') ?: '127.0.0.1';
$port = (int)(getenv('RABBITMQ_PORT') ?: 5672);
$user = getenv('RABBITMQ_USERNAME') ?: 'guest';
$pass = getenv('RABBITMQ_PASSWORD') ?: 'guest';
$queue = 'alert.pest';

try {
    // Buka koneksi ke RabbitMQ
    $connection = new AMQPStreamConnection($host, $port, $user, $pass);
    $channel = $connection->channel();

    $channel->queue_declare($queue, false, true, false, false);

    echo "[*] Menunggu event hama dari Python ML di queue '$queue'. Tekan CTRL+C untuk keluar\n";

    // Definisi tindakan saat pesan masuk
    $callback = function ($msg) {
        echo "[x] Menerima event ML: ", $msg->body, "\n";
        $data = json_decode($msg->body, true);

        if (is_array($data) && !empty($data['zone_id']) && !empty($data['severity'])) {
            $alertModel = new Alert();
            
            $alertData = [
                'zone_id'     => $data['zone_id'],
                'alert_type'  => 'pest',
                'severity'    => $data['severity'],
                'description' => $data['description'] ?? 'Deteksi hama otomatis oleh AI Computer Vision'
            ];

            try {
                $alertModel->create($alertData);
                echo " [v] Alert berhasil disimpan ke Database AgriCity!\n";
                
                $msg->ack();
            } catch (\Exception $e) {
                echo " [!] Gagal menyimpan ke DB: " . $e->getMessage() . "\n";
                $msg->nack(true);
            }
        } else {
            echo " [!] Format payload tidak sesuai standar, pesan diabaikan.\n";
            $msg->ack();
        }
    };

    // Tarik pesan satu-satu dan mulai mendengarkan
    $channel->basic_qos(null, 1, null);
    $channel->basic_consume($queue, '', false, false, false, false, $callback);

    // Biarkan script nyala terus
    while ($channel->is_open()) {
        $channel->wait();
    }

    $channel->close();
    $connection->close();
} catch (\Exception $e) {
    echo "[Error RabbitMQ] " . $e->getMessage() . "\n";
}
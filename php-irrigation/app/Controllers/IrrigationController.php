<?php
namespace App\Controllers;

use App\Models\SensorReading;
use App\Services\RabbitMQPublisher;
use App\Validators\SensorValidator;

class IrrigationController {
    private SensorReading $model;
    private RabbitMQPublisher $publisher;

    public function __construct() {
        $this->model     = new SensorReading();
        $this->publisher = new RabbitMQPublisher();
    }

    public function storeReading(array $data): array {
        // Validasi
        $validated = SensorValidator::validate($data);
        
        // Simpan ke Database
        $record = $this->model->create($validated);

        // Publish ke RabbitMQ untuk Python ML
        $this->publisher->publish('sensor.new', [
            'id'        => $record['id'],
            'zone'      => $record['zone_id'],
            'moisture'  => $record['moisture'],
            'ph'        => $record['ph'],
            'nitrogen'  => $record['nitrogen'],
            'timestamp' => $record['recorded_at'],
        ]);

        return [
            'status' => 'success', 
            'code' => 201, 
            'message' => 'Sensor reading saved and event published',
            'data' => $record
        ];
    }
}
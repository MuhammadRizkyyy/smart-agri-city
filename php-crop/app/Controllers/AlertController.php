<?php
namespace App\Controllers;

use App\Models\Alert;
use App\Services\RabbitMQPublisher;
use App\Validators\InputValidator;

class AlertController {
    private Alert $model;
    private RabbitMQPublisher $publisher;

    public function __construct() {
        $this->model = new Alert();
        $this->publisher = new RabbitMQPublisher();
    }

    public function index(array $queryParams): array {
        $zone_id = $queryParams['zone_id'] ?? null;
        $severity = $queryParams['severity'] ?? null;

        return [
            "status" => "success",
            "code" => 200,
            "data" => $this->model->getAll($zone_id, $severity)
        ];
    }

    public function show(int $id): array {
        $alert = $this->model->getById($id);
        if (!$alert) {
            return ["status" => "error", "code" => 404, "message" => "Alert not found"];
        }
        return ["status" => "success", "code" => 200, "data" => $alert];
    }

    public function store(array $data): array {
        // Eksekusi Validator yang baru dibuat
        $errors = InputValidator::validateAlert($data);
        
        if (!empty($errors)) {
            return [
                "status"  => "error", 
                "code"    => 400, 
                "message" => "Validation failed", 
                "errors"  => $errors
            ];
        }

        // Jika lolos validasi, langsung simpan ke DB
        $record = $this->model->create($data);

        // Publish event ke sistem antrean
        $this->publisher->publish('alert.created', [
            'id'         => $record['id'],
            'zone_id'    => $record['zone_id'],
            'alert_type' => $record['alert_type'],
            'severity'   => $record['severity'],
            'timestamp'  => date('Y-m-d H:i:s')
        ]);

        return [
            "status"  => "success",
            "code"    => 201,
            "message" => "Alert created and event published",
            "data"    => $record
        ];
    }

    public function resolve(int $id): array {
        $existing = $this->model->getById($id);
        if (!$existing) {
            return ["status" => "error", "code" => 404, "message" => "Alert not found"];
        }

        if ($this->model->isResolved($id)) {
            return ["status" => "success", "code" => 200, "message" => "Alert is already resolved"];
        }

        $this->model->resolve($id);
        return [
            "status"  => "success",
            "code"    => 200,
            "message" => "Alert marked as resolved"
        ];
    }
}
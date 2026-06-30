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
        $status = $queryParams['status'] ?? null;

        return [
            "status" => "success",
            "code" => 200,
            "data" => $this->model->getAll($zone_id, $severity, $status)
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
        try {
            $this->publisher->publish('alert.created', [
                'id'         => $record['id'],
                'zone_id'    => $record['zone_id'],
                'alert_type' => $record['alert_type'],
                'severity'   => $record['severity'],
                'timestamp'  => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("[AlertController] RabbitMQ publish failed: " . $e->getMessage());
        }

        return [
            "status"  => "success",
            "code"    => 201,
            "message" => "Alert created and event published",
            "data"    => $record
        ];
    }

    public function resolve(int $id): array {
        set_time_limit(5);
        
        try {
            $existing = $this->model->getById($id);
            if (!$existing) {
                return [
                    "status" => "error",
                    "code" => 404,
                    "message" => "Alert not found"
                ];
            }

            if ($this->model->isResolved($id)) {
                return [
                    "status" => "success",
                    "code" => 200,
                    "message" => "Alert is already resolved"
                ];
            }

            $startTime = microtime(true);
            $this->model->resolve($id);
            $duration = (microtime(true) - $startTime) * 1000;

            error_log("[AlertController] Alert #$id resolved in {$duration}ms");

            return [
                "status"  => "success",
                "code"    => 200,
                "message" => "Alert marked as resolved",
                "data"    => [
                    "alert_id" => $id,
                    "resolved_at" => date('Y-m-d H:i:s'),
                    "duration_ms" => round($duration, 2)
                ]
            ];
            
        } catch (\Exception $e) {
            error_log("[AlertController] Error resolving alert #$id: " . $e->getMessage());
            return [
                "status" => "error",
                "code" => 500,
                "message" => "Failed to resolve alert",
                "error" => $e->getMessage()
            ];
        }
    }
}
<?php
namespace App\Controllers;

use App\Models\CropSchedule;
use App\Services\RabbitMQPublisher;

class CropController {
    private CropSchedule $model;
    private RabbitMQPublisher $publisher;

    public function __construct() {
        $this->model = new CropSchedule();
        $this->publisher = new RabbitMQPublisher();
    }

    public function index(array $queryParams): array {
        $land_id = $queryParams['land_id'] ?? null;
        $growth_phase = $queryParams['growth_phase'] ?? null;
        
        return [
            "status" => "success",
            "code" => 200,
            "data" => $this->model->getAll($land_id, $growth_phase)
        ];
    }

    public function show(int $id): array {
        $crop = $this->model->getById($id);
        if (!$crop) {
            return ["status" => "error", "code" => 404, "message" => "Crop schedule not found"];
        }
        return ["status" => "success", "code" => 200, "data" => $crop];
    }

    public function store(array $data): array {
        // Validasi simpel sesuai kebutuhan
        if (empty($data['land_id']) || empty($data['crop_type']) || empty($data['plant_date'])) {
            return ["status" => "error", "code" => 400, "message" => "Missing required fields (land_id, crop_type, plant_date)"];
        }

        $record = $this->model->create($data);

        // Publish event ke RabbitMQ
        $this->publisher->publish('crop.scheduled', [
            'id'         => $record['id'],
            'land_id'    => $record['land_id'],
            'crop_type'  => $record['crop_type'],
            'plant_date' => $record['plant_date'],
            'timestamp'  => date('Y-m-d H:i:s')
        ]);

        return [
            "status"  => "success",
            "code"    => 201,
            "message" => "Crop schedule created and event published",
            "data"    => $record
        ];
    }

    public function update(int $id, array $data): array {
        $existing = $this->model->getById($id);
        if (!$existing) {
            return ["status" => "error", "code" => 404, "message" => "Crop schedule not found"];
        }

        // Gabungkan data lama dengan data baru
        $updatedData = array_merge($existing, $data);
        $this->model->update($id, $updatedData);

        return [
            "status"  => "success",
            "code"    => 200,
            "message" => "Crop schedule updated successfully",
            "data"    => $updatedData
        ];
    }

    public function destroy(int $id): array {
        $existing = $this->model->getById($id);
        if (!$existing) {
            return ["status" => "error", "code" => 404, "message" => "Crop schedule not found"];
        }

        $this->model->delete($id);
        return ["status" => "success", "code" => 200, "message" => "Crop schedule deleted"];
    }
}
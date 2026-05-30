<?php
namespace App\Controllers;

use App\Models\SensorReading;
use App\Models\Zone;
use App\Validators\SensorValidator;
use App\Services\RabbitMQPublisher;

class SensorController extends BaseController {
    private SensorReading $sensorReadingModel;
    private Zone $zoneModel;
    private RabbitMQPublisher $publisher;

    public function __construct() {
        $this->sensorReadingModel = new SensorReading();
        $this->zoneModel = new Zone();
        $this->publisher = new RabbitMQPublisher();
    }

    public function storeReading(): void {
        $body = $this->getJsonBody();
        if (!$body) {
            $this->error('Invalid JSON payload', 400);
            return;
        }

        $validated = SensorValidator::validate($body);

        $zoneId = intval($body['zone'] ?? $body['zone_id'] ?? 0);
        if ($zoneId <= 0) {
            $this->error('Invalid or missing zone parameter', 400);
            return;
        }

        if (!$this->zoneModel->exists($zoneId)) {
            $this->error("Zone with ID {$zoneId} not found", 404);
            return;
        }

        $validated['zone_id'] = $zoneId;

        try {
            $reading = $this->sensorReadingModel->create($validated);

            $this->publisher->publish('sensor.new', $reading);

            $this->created($reading, 'Sensor reading stored and published successfully');
        } catch (\Exception $e) {
            $this->error('Failed to store sensor reading: ' . $e->getMessage(), 500);
        }
    }

    public function getCurrentReading(): void {
        $zoneId = isset($_GET['zone_id']) ? intval($_GET['zone_id']) : null;

        try {
            if ($zoneId !== null) {
                if (!$this->zoneModel->exists($zoneId)) {
                    $this->error("Zone with ID {$zoneId} not found", 404);
                    return;
                }
                $reading = $this->sensorReadingModel->getLatestForZone($zoneId);
                $this->success($reading, 'Latest reading for zone ' . $zoneId);
            } else {
                $readings = $this->sensorReadingModel->getLatestForAllZones();
                $this->success($readings, 'Latest readings for all zones');
            }
        } catch (\Exception $e) {
            $this->error('Failed to retrieve current readings: ' . $e->getMessage(), 500);
        }
    }

    public function getHistory(): void {
        $filters = [
            'zone_id' => isset($_GET['zone_id']) && $_GET['zone_id'] !== '' ? intval($_GET['zone_id']) : null,
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null
        ];

        try {
            if ($filters['zone_id'] !== null && !$this->zoneModel->exists($filters['zone_id'])) {
                $this->error("Zone with ID {$filters['zone_id']} not found", 404);
                return;
            }
            $history = $this->sensorReadingModel->getHistory($filters);
            $this->success($history, 'Sensor history retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve history: ' . $e->getMessage(), 500);
        }
    }
}

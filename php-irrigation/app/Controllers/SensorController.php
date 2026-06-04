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
        $this->zoneModel          = new Zone();
        $this->publisher          = new RabbitMQPublisher();
    }

    /**
     * POST /iot/sensor  (atau /sensor)
     * Endpoint utama dari Node-RED — simpan data sensor, publish sensor.new ke RabbitMQ.
     */
    public function storeReading(): void {
        $body = $this->getJsonBody();
        if (!$body) {
            $this->error('Invalid JSON payload', 400);
            return;
        }

        // Ambil zone_id dari field "zone" atau "zone_id"
        $zoneId = intval($body['zone'] ?? $body['zone_id'] ?? 0);
        if ($zoneId <= 0) {
            $this->error('Invalid or missing zone/zone_id parameter', 400);
            return;
        }

        if (!$this->zoneModel->exists($zoneId)) {
            $this->error("Zone with ID {$zoneId} not found", 404);
            return;
        }

        // Validasi dan normalisasi field sensor
        $validated            = SensorValidator::validate($body);
        $validated['zone_id'] = $zoneId;

        try {
            $reading = $this->sensorReadingModel->create($validated);

            // Publish ke RabbitMQ dengan field yang sesuai Python ML sensor_consumer.py
            $this->publisher->publish('sensor.new', [
                'id'               => $reading['id'],
                'zone'             => (string)$zoneId,
                'zone_id'          => $zoneId,
                'soil_moisture'    => $reading['moisture'],       // Python expect soil_moisture
                'moisture'         => $reading['moisture'],
                'temperature'      => $reading['temperature'],
                'air_temp'         => $reading['air_temp'],       // Python expect air_temp
                'ph'               => $reading['ph'],
                'soil_ph'          => $reading['ph'],             // Python expect soil_ph untuk pest
                'nitrogen'         => $reading['nitrogen'],
                'phosphorus'       => $reading['phosphorus'],
                'potassium'        => $reading['potassium'],
                'air_humidity'     => $reading['air_humidity'],
                'light_lux'        => $reading['light_lux'],
                // Field dengan nilai default untuk Python ML
                'leaf_temp'        => $reading['air_temp'],
                'chlorophyll'      => 45.0,
                'rain_forecast'    => 10.0,
                'growth_phase'     => 'Vegetatif',
                'evapotranspiration' => 4.5,
                'rainfall'         => 1200.0,
                'area_ha'          => 1.5,
                'week_of_planting' => 8,
                'recorded_at'      => $reading['recorded_at'],
                'timestamp'        => $reading['recorded_at'],
            ]);

            $this->created($reading, 'Sensor reading stored and published successfully');
        } catch (\Exception $e) {
            $this->error('Failed to store sensor reading: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /sensors/current?zone_id=1
     */
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

    /**
     * GET /sensors/{id} — detail satu reading
     */
    public function getReadingById(int $id): void {
        try {
            $reading = $this->sensorReadingModel->getById($id);
            if (!$reading) {
                $this->error("Sensor reading with ID {$id} not found", 404);
                return;
            }
            $this->success($reading, 'Sensor reading retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve reading: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /sensors/history?zone_id=1&start_date=2026-01-01&end_date=2026-06-01
     */
    public function getHistory(): void {
        $filters = [
            'zone_id'    => isset($_GET['zone_id']) && $_GET['zone_id'] !== '' ? intval($_GET['zone_id']) : null,
            'start_date' => $_GET['start_date'] ?? null,
            'end_date'   => $_GET['end_date']   ?? null,
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

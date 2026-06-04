<?php
namespace App\Controllers;

use App\Models\Zone;
use App\Models\SensorReading;
use App\Models\IrrigationLog;

class ZoneController extends BaseController {
    private Zone $zoneModel;
    private SensorReading $sensorReadingModel;
    private IrrigationLog $irrigationLogModel;

    public function __construct() {
        $this->zoneModel          = new Zone();
        $this->sensorReadingModel = new SensorReading();
        $this->irrigationLogModel = new IrrigationLog();
    }

    /**
     * GET /zones
     * List semua zona dengan status real-time (sensor terkini + status irigasi).
     */
    public function index(): void {
        try {
            $zones      = $this->zoneModel->getAll();
            $activeLogs = $this->irrigationLogModel->getAllActiveLogs();

            // Index active logs by zone_id for O(1) lookup
            $activeByZone = [];
            foreach ($activeLogs as $log) {
                $activeByZone[(int)$log['zone_id']] = $log;
            }

            $result = [];
            foreach ($zones as $zone) {
                $zId            = (int)$zone['id'];
                $latestSensor   = $this->sensorReadingModel->getLatestForZone($zId);
                $isIrrigating   = isset($activeByZone[$zId]);

                $result[] = array_merge($zone, [
                    'irrigation_status' => $isIrrigating ? 'active' : 'inactive',
                    'active_irrigation' => $isIrrigating ? $activeByZone[$zId] : null,
                    'latest_sensor'     => $latestSensor,
                ]);
            }

            $this->success($result, 'Zones retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve zones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /zones/{id}
     * Detail zona + sensor terkini + status irigasi.
     */
    public function show(int $id): void {
        try {
            $zone = $this->zoneModel->getById($id);
            if (!$zone) {
                $this->error("Zone with ID {$id} not found", 404);
                return;
            }

            $latestSensor  = $this->sensorReadingModel->getLatestForZone($id);
            $activeLog     = $this->irrigationLogModel->findActiveLog($id);
            $recentLogs    = $this->irrigationLogModel->getLogs(['zone_id' => $id, 'start_date' => null, 'end_date' => null]);
            // Batasi 10 log terbaru
            $recentLogs    = array_slice($recentLogs, 0, 10);

            $this->success(array_merge($zone, [
                'irrigation_status'  => $activeLog ? 'active' : 'inactive',
                'active_irrigation'  => $activeLog,
                'latest_sensor'      => $latestSensor,
                'recent_irrigation_logs' => $recentLogs,
            ]), "Zone {$id} details retrieved successfully");
        } catch (\Exception $e) {
            $this->error('Failed to retrieve zone: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /zones
     * Tambah zona baru.
     * Body: { name, area_ha, lat, lng, status? }
     */
    public function store(): void {
        $body = $this->getJsonBody();
        if (!$body) {
            $this->error('Invalid JSON payload', 400);
            return;
        }

        // Validasi field wajib
        $required = ['name', 'area_ha', 'lat', 'lng'];
        foreach ($required as $field) {
            if (!isset($body[$field]) || $body[$field] === '') {
                $this->error("Field '{$field}' is required", 400);
                return;
            }
        }

        $data = [
            'name'    => trim($body['name']),
            'area_ha' => floatval($body['area_ha']),
            'lat'     => floatval($body['lat']),
            'lng'     => floatval($body['lng']),
            'status'  => in_array($body['status'] ?? 'active', ['active', 'inactive', 'maintenance'])
                            ? $body['status']
                            : 'active',
        ];

        if ($data['area_ha'] <= 0) {
            $this->error("area_ha must be greater than 0", 400);
            return;
        }

        try {
            $zone = $this->zoneModel->create($data);
            $this->created($zone, 'Zone created successfully');
        } catch (\Exception $e) {
            $this->error('Failed to create zone: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /zones/{id}
     * Update status atau atribut zona.
     * Body: { name?, area_ha?, status?, lat?, lng? }
     */
    public function update(int $id): void {
        $zone = $this->zoneModel->getById($id);
        if (!$zone) {
            $this->error("Zone with ID {$id} not found", 404);
            return;
        }

        $body = $this->getJsonBody();
        if (!$body) {
            $this->error('Invalid JSON payload', 400);
            return;
        }

        $updateData = [];
        if (isset($body['name']))    $updateData['name']    = trim($body['name']);
        if (isset($body['area_ha'])) $updateData['area_ha'] = floatval($body['area_ha']);
        if (isset($body['lat']))     $updateData['lat']     = floatval($body['lat']);
        if (isset($body['lng']))     $updateData['lng']     = floatval($body['lng']);
        if (isset($body['status'])) {
            if (!in_array($body['status'], ['active', 'inactive', 'maintenance'])) {
                $this->error("Invalid status. Allowed: active, inactive, maintenance", 400);
                return;
            }
            $updateData['status'] = $body['status'];
        }

        if (empty($updateData)) {
            $this->error('No valid fields to update', 400);
            return;
        }

        try {
            $updated = $this->zoneModel->update($id, $updateData);
            $this->success($updated, "Zone {$id} updated successfully");
        } catch (\Exception $e) {
            $this->error('Failed to update zone: ' . $e->getMessage(), 500);
        }
    }
}

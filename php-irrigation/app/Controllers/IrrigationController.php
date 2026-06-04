<?php
namespace App\Controllers;

use App\Models\IrrigationLog;
use App\Models\Zone;

class IrrigationController extends BaseController {
    private IrrigationLog $irrigationLogModel;
    private Zone $zoneModel;

    public function __construct() {
        $this->irrigationLogModel = new IrrigationLog();
        $this->zoneModel = new Zone();
    }

    public function getStatus(): void {
        $zoneId = isset($_GET['zone_id']) ? intval($_GET['zone_id']) : null;

        try {
            if ($zoneId !== null) {
                if (!$this->zoneModel->exists($zoneId)) {
                    $this->error("Zone with ID {$zoneId} not found", 404);
                    return;
                }
                $activeLog = $this->irrigationLogModel->findActiveLog($zoneId);
                $this->success([
                    'zone_id' => $zoneId,
                    'status' => $activeLog ? 'active' : 'inactive',
                    'active_log' => $activeLog
                ], "Irrigation status for zone {$zoneId}");
            } else {
                $zones = $this->zoneModel->getAll();
                $activeLogs = $this->irrigationLogModel->getAllActiveLogs();
                
                $activeLogsByZone = [];
                foreach ($activeLogs as $log) {
                    $activeLogsByZone[$log['zone_id']] = $log;
                }

                $statusList = [];
                foreach ($zones as $zone) {
                    $zId = intval($zone['id']);
                    $isActive = isset($activeLogsByZone[$zId]);
                    $statusList[] = [
                        'zone_id' => $zId,
                        'zone_name' => $zone['name'],
                        'status' => $isActive ? 'active' : 'inactive',
                        'active_log' => $isActive ? $activeLogsByZone[$zId] : null
                    ];
                }

                $this->success($statusList, 'Irrigation status for all zones');
            }
        } catch (\Exception $e) {
            $this->error('Failed to retrieve irrigation status: ' . $e->getMessage(), 500);
        }
    }

    public function handleCommand(): void {
        $body = $this->getJsonBody();
        if (!$body) {
            $this->error('Invalid JSON payload', 400);
            return;
        }

        $zoneId = isset($body['zone_id']) ? intval($body['zone_id']) : null;
        $action = isset($body['action']) ? trim(strtolower($body['action'])) : null;
        $triggerType = isset($body['trigger_type']) ? trim($body['trigger_type']) : 'manual';
        $volumeLiters = isset($body['volume_liters']) ? floatval($body['volume_liters']) : 0.0;

        if ($zoneId === null || $zoneId <= 0) {
            $this->error('Missing or invalid zone_id', 400);
            return;
        }

        if (!$this->zoneModel->exists($zoneId)) {
            $this->error("Zone with ID {$zoneId} not found", 404);
            return;
        }

        if ($action !== 'start' && $action !== 'stop') {
            $this->error("Invalid action '{$action}'. Action must be 'start' or 'stop'", 400);
            return;
        }

        $validTriggers = ['manual', 'otomatis_ml', 'otomatis_jadwal'];
        if (!in_array($triggerType, $validTriggers)) {
            $this->error("Invalid trigger_type '{$triggerType}'. Allowed values: manual, otomatis_ml, otomatis_jadwal", 400);
            return;
        }

        try {
            $activeLog = $this->irrigationLogModel->findActiveLog($zoneId);

            if ($action === 'start') {
                if ($activeLog) {
                    $this->error("Irrigation is already active/started for zone {$zoneId}", 400, [
                        'active_log' => $activeLog
                    ]);
                    return;
                }

                $newLog = $this->irrigationLogModel->startIrrigation($zoneId, $triggerType);
                $this->created($newLog, "Irrigation started successfully for zone {$zoneId}");
            } else {
                if (!$activeLog) {
                    $this->error("No active irrigation session found for zone {$zoneId} to stop", 400);
                    return;
                }

                $stoppedLog = $this->irrigationLogModel->stopIrrigation($zoneId, $volumeLiters);
                $this->success($stoppedLog, "Irrigation stopped successfully for zone {$zoneId}");
            }
        } catch (\Exception $e) {
            $this->error('Failed to execute irrigation command: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /irrigation/{id}
     * Update status log irigasi (misal: set volume setelah selesai).
     */
    public function updateLog(int $id): void {
        $body = $this->getJsonBody();
        if (!$body) {
            $this->error('Invalid JSON payload', 400);
            return;
        }

        try {
            $updated = $this->irrigationLogModel->updateById($id, $body);
            if (!$updated) {
                $this->error("Irrigation log with ID {$id} not found", 404);
                return;
            }
            $this->success($updated, "Irrigation log {$id} updated successfully");
        } catch (\Exception $e) {
            $this->error('Failed to update irrigation log: ' . $e->getMessage(), 500);
        }
    }

    public function getLogs(): void {
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
            $logs = $this->irrigationLogModel->getLogs($filters);
            $this->success($logs, 'Irrigation logs retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve irrigation logs: ' . $e->getMessage(), 500);
        }
    }
}
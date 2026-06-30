<?php
namespace App\Controllers;

use App\Models\IrrigationLog;
use App\Models\Zone;
use App\Services\RabbitMQPublisher;

class IrrigationController extends BaseController {
    private IrrigationLog $irrigationLogModel;
    private Zone $zoneModel;
    private RabbitMQPublisher $rabbitMQ;

    public function __construct() {
        $this->irrigationLogModel = new IrrigationLog();
        $this->zoneModel = new Zone();
        $this->rabbitMQ = new RabbitMQPublisher();
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
        $logFile = '/tmp/irrigation_controller.log'; 
        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] handleCommand called\n", FILE_APPEND);
        
        try {
            $body = $this->getJsonBody();
            if (!$body) {
                $this->error('Invalid JSON payload', 400);
                return;
            }

            $zoneId = isset($body['zone_id']) ? intval($body['zone_id']) : null;
            $action = isset($body['action']) ? trim(strtolower($body['action'])) : null;
            $triggerType = isset($body['trigger_type']) ? trim($body['trigger_type']) : 'manual';

            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Action=$action, Zone=$zoneId\n", FILE_APPEND);

            // Minimal validation
            if ($zoneId === null || $zoneId <= 0) {
                $this->error('Missing or invalid zone_id', 400);
                return;
            }

            if ($action !== 'start' && $action !== 'stop') {
                $this->error("Invalid action. Must be 'start' or 'stop'", 400);
                return;
            }

            $logEntry = null;
            
            if ($action === 'start') {
                $activeLog = $this->irrigationLogModel->findActiveLog($zoneId);
                if ($activeLog) {
                    $this->success([
                        'zone_id' => $zoneId,
                        'action' => $action,
                        'status' => 'already_active',
                        'message' => "Zone {$zoneId} already has active irrigation",
                        'active_log_id' => $activeLog['id']
                    ], "Zone already irrigating");
                    return;
                }
                // Create new irrigation log
                $logEntry = $this->irrigationLogModel->startIrrigation($zoneId, $triggerType);
                @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] START - Log created id={$logEntry['id']}\n", FILE_APPEND);
            } elseif ($action === 'stop') {
                @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] STOP action detected\n", FILE_APPEND);
                // Find and close active irrigation
                $activeLog = $this->irrigationLogModel->findActiveLog($zoneId);
                @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Active log found: " . ($activeLog ? $activeLog['id'] : 'NONE') . "\n", FILE_APPEND);
                
                if (!$activeLog) {
                    $this->success([
                        'zone_id' => $zoneId,
                        'action' => $action,
                        'status' => 'not_active',
                        'message' => "Zone {$zoneId} has no active irrigation"
                    ], "No active irrigation to stop");
                    return;
                }
                // Stop irrigation - volume will be auto-calculated based on duration and flow rate
                @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Calling stopIrrigation...\n", FILE_APPEND);
                $logEntry = $this->irrigationLogModel->stopIrrigation($zoneId);
                @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] stopIrrigation returned, volume=" . ($logEntry['volume_liters'] ?? 'NULL') . "\n", FILE_APPEND);
            }

            // Publish to iot.valve for Node-RED valve control
            try {
                $this->rabbitMQ->publish('iot.valve', [
                    'zone_id' => $zoneId,
                    'action' => $action === 'start' ? 'open' : 'close',
                    'trigger_type' => $triggerType,
                    'log_id' => $logEntry['id'] ?? null,
                    'timestamp' => date('c'),
                    'source' => 'manual_command'
                ]);
            } catch (\Exception $mqE) {
                error_log("[IrrigationController] Warning: RabbitMQ publish failed: " . $mqE->getMessage());
                // Don't fail the request - database is already updated
            }

            // Success response
            $this->success([
                'zone_id' => $zoneId,
                'action' => $action,
                'status' => 'success',
                'log_id' => $logEntry['id'] ?? null,
                'message' => "Irrigation {$action} command processed for zone {$zoneId}"
            ], "Irrigation command processed successfully");
            
        } catch (\Exception $e) {
            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            error_log("[IrrigationController::handleCommand] Error: " . $e->getMessage());
            $this->error('Command failed: ' . $e->getMessage(), 500);
        }
    }

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
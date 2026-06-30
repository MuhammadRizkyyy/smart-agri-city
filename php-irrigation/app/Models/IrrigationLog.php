<?php
namespace App\Models;

class IrrigationLog {
    private \PDO $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function findActiveLog(int $zoneId): ?array {
        // Find the MOST RECENT active log (ORDER BY id DESC) not the oldest
        $query = "SELECT * FROM irr_irrigation_logs WHERE zone_id = :zone_id AND ended_at IS NULL ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':zone_id' => $zoneId]);
        $log = $stmt->fetch();
        return $log ?: null;
    }

    public function startIrrigation(int $zoneId, string $triggerType): array {
        $query = "INSERT INTO irr_irrigation_logs (zone_id, started_at, trigger_type) 
                  VALUES (:zone_id, CURRENT_TIMESTAMP, :trigger_type)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':zone_id'      => $zoneId,
            ':trigger_type' => $triggerType,
        ]);

        $id = (int)$this->db->lastInsertId();

        $getStmt = $this->db->prepare("SELECT * FROM irr_irrigation_logs WHERE id = :id");
        $getStmt->execute([':id' => $id]);
        return $getStmt->fetch();
    }

    public function stopIrrigation(int $zoneId, float $volumeLiters = null): ?array {
        $logFile = '/tmp/irrigation_model.log';
        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] stopIrrigation called: zone=$zoneId, vol=$volumeLiters\n", FILE_APPEND);
        
        $activeLog = $this->findActiveLog($zoneId);
        if (!$activeLog) {
            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] No active log found\n", FILE_APPEND);
            return null;
        }

        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Found log id={$activeLog['id']}\n", FILE_APPEND);

        // If volume not provided, calculate from duration and zone flow rate
        if ($volumeLiters === null) {
            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Calculating volume...\n", FILE_APPEND);
            $volumeLiters = $this->calculateVolumeFromDatabase($zoneId, $activeLog['id']);
            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Calculated: $volumeLiters L\n", FILE_APPEND);
        }

        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Updating DB with volume=$volumeLiters\n", FILE_APPEND);
        
        $query = "UPDATE irr_irrigation_logs 
                  SET ended_at = CURRENT_TIMESTAMP, volume_liters = :volume_liters 
                  WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            ':volume_liters' => $volumeLiters,
            ':id'            => $activeLog['id'],
        ]);

        $getStmt = $this->db->prepare("SELECT * FROM irr_irrigation_logs WHERE id = :id");
        $getStmt->execute([':id' => $activeLog['id']]);
        $updated = $getStmt->fetch();
        
        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Final volume in DB: {$updated['volume_liters']}\n", FILE_APPEND);
        
        return $updated;
    }

    /**
     * Calculate volume directly from database using SQL TIMESTAMPDIFF
     * This avoids timezone issues with PHP DateTime
     */
    private function calculateVolumeFromDatabase(int $zoneId, int $logId): float {
        // Use a single query to get flow rate and calculate duration in minutes
        $query = "SELECT 
                    z.flow_rate_liters_per_minute,
                    TIMESTAMPDIFF(MINUTE, l.started_at, CURRENT_TIMESTAMP) as duration_minutes,
                    TIMESTAMPDIFF(SECOND, l.started_at, CURRENT_TIMESTAMP) as duration_seconds
                  FROM irr_irrigation_logs l
                  JOIN irr_zones z ON l.zone_id = z.id
                  WHERE l.id = :log_id AND l.zone_id = :zone_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':log_id' => $logId,
            ':zone_id' => $zoneId
        ]);
        
        $result = $stmt->fetch();
        
        if (!$result) {
            error_log("[calculateVolumeFromDatabase] No result for log_id=$logId, zone_id=$zoneId");
            return 0.0;
        }

        $flowRate = (float)$result['flow_rate_liters_per_minute'];
        $durationMinutes = (float)$result['duration_minutes'];
        $durationSeconds = (int)$result['duration_seconds'];
        
        // Add seconds as fraction of minute (more accurate than just minutes)
        $totalMinutes = $durationMinutes + ($durationSeconds % 60) / 60;
        
        // Calculate volume
        $volume = $totalMinutes * $flowRate;
        
        error_log("[calculateVolumeFromDatabase] Zone $zoneId: flowRate=$flowRate L/min, duration=$totalMinutes min, volume=$volume L");
        
        return round($volume, 2);
    }

    public function getLogs(array $filters): array {
        $query  = "SELECT * FROM irr_irrigation_logs WHERE 1=1";
        $params = [];

        if (!empty($filters['zone_id'])) {
            $query .= " AND zone_id = :zone_id";
            $params[':zone_id'] = (int)$filters['zone_id'];
        }

        if (!empty($filters['start_date'])) {
            $query .= " AND started_at >= :start_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND started_at <= :end_date";
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        $query .= " ORDER BY started_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAllActiveLogs(): array {
        $query = "SELECT * FROM irr_irrigation_logs WHERE ended_at IS NULL";
        $stmt  = $this->db->query($query);
        return $stmt->fetchAll();
    }

    public function updateById(int $id, array $data): ?array {
        // Cek record ada
        $check = $this->db->prepare("SELECT id FROM irr_irrigation_logs WHERE id = :id");
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            return null;
        }

        $fields = [];
        $params = [':id' => $id];

        $allowed = ['ended_at', 'volume_liters', 'trigger_type'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[]          = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (!empty($fields)) {
            $query = "UPDATE irr_irrigation_logs SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt  = $this->db->prepare($query);
            $stmt->execute($params);
        }

        $getStmt = $this->db->prepare("SELECT * FROM irr_irrigation_logs WHERE id = :id");
        $getStmt->execute([':id' => $id]);
        return $getStmt->fetch() ?: null;
    }
}

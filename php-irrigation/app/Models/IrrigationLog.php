<?php
namespace App\Models;

class IrrigationLog {
    private \PDO $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function findActiveLog(int $zoneId): ?array {
        $query = "SELECT * FROM irr_irrigation_logs WHERE zone_id = :zone_id AND ended_at IS NULL LIMIT 1";
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

    public function stopIrrigation(int $zoneId, float $volumeLiters): ?array {
        $activeLog = $this->findActiveLog($zoneId);
        if (!$activeLog) {
            return null;
        }

        $query = "UPDATE irr_irrigation_logs 
                  SET ended_at = CURRENT_TIMESTAMP, volume_liters = :volume_liters 
                  WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':volume_liters' => $volumeLiters,
            ':id'            => $activeLog['id'],
        ]);

        $getStmt = $this->db->prepare("SELECT * FROM irr_irrigation_logs WHERE id = :id");
        $getStmt->execute([':id' => $activeLog['id']]);
        return $getStmt->fetch();
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

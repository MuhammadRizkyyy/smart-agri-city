<?php
namespace App\Models;

class SensorReading {
    private \PDO $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function create(array $data): array {
        $query = "INSERT INTO irr_sensor_readings 
                  (zone_id, moisture, temperature, ph, nitrogen, phosphorus, potassium) 
                      VALUES (:zone_id, :moisture, :temperature, :ph, :nitrogen, :phosphorus, :potassium)";
        
        $stmt = $this->db->prepare($query);
        
        $stmt->execute([
            ':zone_id'    => $data['zone_id'],
                ':moisture'   => $data['moisture'] ?? 0,
                ':temperature'=> $data['temperature'] ?? 0, // Fallback 0 jika payload Node-RED tidak ada
                ':ph'         => $data['ph'] ?? 0,
                ':nitrogen'   => $data['nitrogen'] ?? 0,
                ':phosphorus' => $data['phosphorus'] ?? 0,
                ':potassium'  => $data['potassium'] ?? 0
        ]);

        $data['id'] = $this->db->lastInsertId();
        $data['recorded_at'] = date('Y-m-d H:i:s');
        
        return $data;
    }

    public function getLatestForZone(int $zoneId): ?array {
        $query = "SELECT * FROM irr_sensor_readings WHERE zone_id = :zone_id ORDER BY recorded_at DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':zone_id' => $zoneId]);
        $reading = $stmt->fetch();
        return $reading ?: null;
    }

    public function getLatestForAllZones(): array {
        $query = "SELECT sr1.* 
                  FROM irr_sensor_readings sr1
                  INNER JOIN (
                      SELECT zone_id, MAX(recorded_at) as max_recorded_at
                      FROM irr_sensor_readings
                      GROUP BY zone_id
                  ) sr2 ON sr1.zone_id = sr2.zone_id AND sr1.recorded_at = sr2.max_recorded_at
                  ORDER BY sr1.zone_id ASC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }

    public function getHistory(array $filters): array {
        $query = "SELECT * FROM irr_sensor_readings WHERE 1=1";
        $params = [];

        if (isset($filters['zone_id']) && $filters['zone_id'] !== '') {
            $query .= " AND zone_id = :zone_id";
            $params[':zone_id'] = (int)$filters['zone_id'];
        }

        if (isset($filters['start_date']) && $filters['start_date'] !== '') {
            $query .= " AND recorded_at >= :start_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
        }

        if (isset($filters['end_date']) && $filters['end_date'] !== '') {
            $query .= " AND recorded_at <= :end_date";
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        $query .= " ORDER BY recorded_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
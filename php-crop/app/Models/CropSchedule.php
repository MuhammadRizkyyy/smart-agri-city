<?php
namespace App\Models;

use PDO;

class CropSchedule {
    private PDO $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getAll(?string $land_id, ?string $growth_phase): array {
        $sql = "SELECT * FROM crp_crop_schedules WHERE 1=1";
        $params = [];

        if ($land_id) {
            $sql .= " AND land_id = :land_id";
            $params[':land_id'] = $land_id;
        }
        if ($growth_phase) {
            $sql .= " AND growth_phase = :growth_phase";
            $params[':growth_phase'] = $growth_phase;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM crp_crop_schedules WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): array {
        $sql = "INSERT INTO crp_crop_schedules (land_id, crop_type, plant_date, growth_phase, expected_harvest) 
                VALUES (:land_id, :crop_type, :plant_date, :growth_phase, :expected_harvest)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':land_id'          => $data['land_id'],
            ':crop_type'        => $data['crop_type'],
            ':plant_date'       => $data['plant_date'],
            ':growth_phase'     => $data['growth_phase'] ?? 'Initial',
            ':expected_harvest' => $data['expected_harvest'] ?? date('Y-m-d', strtotime($data['plant_date'] . ' +90 days'))
        ]);

        $data['id'] = $this->db->lastInsertId();
        return $data;
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE crp_crop_schedules 
                SET land_id = :land_id, crop_type = :crop_type, plant_date = :plant_date, 
                    growth_phase = :growth_phase, expected_harvest = :expected_harvest 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id'               => $id,
            ':land_id'          => $data['land_id'],
            ':crop_type'        => $data['crop_type'],
            ':plant_date'       => $data['plant_date'],
            ':growth_phase'     => $data['growth_phase'],
            ':expected_harvest' => $data['expected_harvest']
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM crp_crop_schedules WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
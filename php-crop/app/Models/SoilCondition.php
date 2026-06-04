<?php
namespace App\Models;

use PDO;

class SoilCondition {
    private PDO $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getAll(?string $land_id): array {
        $sql = "SELECT * FROM crp_soil_conditions WHERE 1=1";
        $params = [];
        
        if ($land_id) {
            $sql .= " AND land_id = :land_id"; 
            $params[':land_id'] = $land_id; 
        }
        
        $sql .= " ORDER BY recorded_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): array {
        $sql = "INSERT INTO crp_soil_conditions (land_id, ph, nitrogen, phosphorus, potassium) 
                VALUES (:land_id, :ph, :nitrogen, :phosphorus, :potassium)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':land_id'    => $data['land_id'],
            ':ph'         => floatval($data['ph'] ?? 0),
            ':nitrogen'   => floatval($data['nitrogen'] ?? 0),
            ':phosphorus' => floatval($data['phosphorus'] ?? 0),
            ':potassium'  => floatval($data['potassium'] ?? 0)
        ]);

        $data['id'] = $this->db->lastInsertId();
        return $data;
    }
}
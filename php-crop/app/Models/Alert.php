<?php
namespace App\Models;

use PDO;

class Alert {
    private PDO $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getAll(?string $zone_id, ?string $severity): array {
        $sql = "SELECT * FROM alerts WHERE 1=1";
        $params = [];
        
        if ($zone_id) { 
            $sql .= " AND zone_id = :zone_id"; 
            $params[':zone_id'] = $zone_id; 
        }
        if ($severity) { 
            $sql .= " AND severity = :severity"; 
            $params[':severity'] = $severity; 
        }
        
        // Urutkan dari yang terbaru
        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM alerts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): array {
        $sql = "INSERT INTO alerts (zone_id, alert_type, severity, description, status) 
                VALUES (:zone_id, :alert_type, :severity, :description, 'active')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':zone_id'     => $data['zone_id'],
            ':alert_type'  => $data['alert_type'], // contoh: 'pest', 'disease', 'ph_extreme'
            ':severity'    => $data['severity'],   // 'low', 'medium', 'high', 'critical'
            ':description' => $data['description'] ?? ''
        ]);

        $data['id'] = $this->db->lastInsertId();
        $data['status'] = 'active';
        return $data;
    }

    public function resolve(int $id): bool {
        $stmt = $this->db->prepare("UPDATE alerts SET status = 'resolved' WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
<?php
namespace App\Models;

use PDO;

class Alert {
    private PDO $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function getAll(?string $zone_id, ?string $severity): array {
        $sql = "SELECT 
                    id, zone_id, alert_type, severity, description,
                    CASE WHEN resolved_at IS NULL THEN 'active' ELSE 'resolved' END AS status,
                    resolved_at,
                    created_at
                FROM crp_alerts WHERE 1=1";
        $params = [];

        if ($zone_id) {
            $sql .= " AND zone_id = :zone_id";
            $params[':zone_id'] = $zone_id;
        }
        if ($severity) {
            $sql .= " AND severity = :severity";
            $params[':severity'] = $severity;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT 
                id, zone_id, alert_type, severity, description,
                CASE WHEN resolved_at IS NULL THEN 'active' ELSE 'resolved' END AS status,
                resolved_at,
                created_at
             FROM crp_alerts WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): array {
        $sql = "INSERT INTO crp_alerts (zone_id, alert_type, severity, description) 
                VALUES (:zone_id, :alert_type, :severity, :description)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':zone_id'     => $data['zone_id'],
            ':alert_type'  => $data['alert_type'],
            ':severity'    => $data['severity'],
            ':description' => $data['description'] ?? ''
        ]);

        $data['id']          = (int) $this->db->lastInsertId();
        $data['status']      = 'active';
        $data['resolved_at'] = null;
        return $data;
    }

    public function resolve(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE crp_alerts SET resolved_at = NOW() WHERE id = :id AND resolved_at IS NULL"
        );
        return $stmt->execute([':id' => $id]);
    }

    public function isResolved(int $id): bool {
        $stmt = $this->db->prepare(
            "SELECT resolved_at FROM crp_alerts WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row && $row['resolved_at'] !== null;
    }
}

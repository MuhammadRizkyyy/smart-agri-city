<?php
namespace App\Models;

class Zone {
    private \PDO $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function exists(int $id): bool {
        $query = "SELECT COUNT(*) FROM irr_zones WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getAll(): array {
        $query = "SELECT * FROM irr_zones ORDER BY id ASC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $query = "SELECT * FROM irr_zones WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $id]);
        $zone = $stmt->fetch();
        return $zone ?: null;
    }
}

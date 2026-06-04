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

    /**
     * Cari zone_id berdasarkan nama (case-insensitive, partial match).
     * Digunakan untuk resolve nama zona dari simulator ("zone-a", "zona1") ke integer ID.
     * Mengembalikan 0 jika tidak ditemukan.
     */
    public function findIdByName(string $name): int {
        $query = "SELECT id FROM irr_zones WHERE LOWER(name) LIKE :name ORDER BY id ASC LIMIT 1";
        $stmt  = $this->db->prepare($query);
        $stmt->execute([':name' => '%' . strtolower(trim($name)) . '%']);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : 0;
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

    public function create(array $data): array {
        $query = "INSERT INTO irr_zones (name, area_ha, status, lat, lng)
                  VALUES (:name, :area_ha, :status, :lat, :lng)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':name'    => $data['name'],
            ':area_ha' => $data['area_ha'],
            ':status'  => $data['status'] ?? 'active',
            ':lat'     => $data['lat'],
            ':lng'     => $data['lng'],
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->getById($id);
    }

    public function update(int $id, array $data): ?array {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['name', 'area_ha', 'status', 'lat', 'lng'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return $this->getById($id);
        }

        $query = "UPDATE irr_zones SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $this->getById($id);
    }
}

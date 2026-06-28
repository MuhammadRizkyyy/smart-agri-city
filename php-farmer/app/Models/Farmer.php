<?php

namespace App\Models;

require_once __DIR__ . '/../Services/Database.php';

use App\Services\Database;
use PDO;

class Farmer
{
    public function getAll(int $page = 1, int $perPage = 10): array
    {
        $db     = Database::connect();
        $offset = ($page - 1) * $perPage;

        $countStmt = $db->query("
            SELECT COUNT(*) AS total
            FROM frm_farmers
            WHERE deleted_at IS NULL
        ");
        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT id, name, email, nik, phone, address, role, created_at, updated_at
            FROM frm_farmers
            WHERE deleted_at IS NULL
            ORDER BY id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items'       => $data,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function getById(int $id): ?array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT id, name, email, nik, phone, address, role, created_at, updated_at
            FROM frm_farmers
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $farmer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$farmer) {
            return null;
        }

        $landStmt = $db->prepare("
            SELECT id, name, area_ha, soil_type, lat, lng, zone_id, created_at
            FROM frm_lands
            WHERE farmer_id = :farmer_id
            ORDER BY id ASC
        ");
        $landStmt->execute([':farmer_id' => $id]);
        $farmer['lands'] = $landStmt->fetchAll(PDO::FETCH_ASSOC);

        return $farmer;
    }

    public function getByPhone(string $phone): ?array
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT id, name, email, nik, phone, address, role, created_at, updated_at
            FROM frm_farmers
            WHERE phone = :phone
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':phone' => $phone]);
        $farmer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$farmer) {
            return null;
        }

        $landStmt = $db->prepare("
            SELECT id, name, area_ha, soil_type, lat, lng, zone_id, created_at
            FROM frm_lands
            WHERE farmer_id = :farmer_id
            ORDER BY id ASC
        ");
        $landStmt->execute([':farmer_id' => $farmer['id']]);
        $farmer['lands'] = $landStmt->fetchAll(PDO::FETCH_ASSOC);

        return $farmer;
    }

    public function create(array $data): int
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO frm_farmers
                (name, email, password, nik, phone, address, role)
            VALUES
                (:name, :email, :password, :nik, :phone, :address, :role)
        ");

        $stmt->execute([
            ':name'     => $data['name'],
            ':email'    => $data['email']    ?? null,
            ':password' => isset($data['password'])
                           ? password_hash($data['password'], PASSWORD_BCRYPT)
                           : null,
            ':nik'      => $data['nik'],
            ':phone'    => $data['phone'],
            ':address'  => $data['address'],
            ':role'     => $data['role']     ?? 'petani',
        ]);

        return (int) $db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $db = Database::connect();

        $hasPassword = !empty($data['password']);

        if ($hasPassword) {
            $stmt = $db->prepare("
                UPDATE frm_farmers
                SET name     = :name,
                    email    = :email,
                    password = :password,
                    nik      = :nik,
                    phone    = :phone,
                    address  = :address,
                    role     = :role
                WHERE id = :id
                  AND deleted_at IS NULL
            ");
            return $stmt->execute([
                ':name'     => $data['name'],
                ':email'    => $data['email']   ?? null,
                ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
                ':nik'      => $data['nik'],
                ':phone'    => $data['phone'],
                ':address'  => $data['address'],
                ':role'     => $data['role']    ?? 'petani',
                ':id'       => $id,
            ]);
        }

        $stmt = $db->prepare("
            UPDATE frm_farmers
            SET name    = :name,
                email   = :email,
                nik     = :nik,
                phone   = :phone,
                address = :address,
                role    = :role
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        return $stmt->execute([
            ':name'    => $data['name'],
            ':email'   => $data['email']  ?? null,
            ':nik'     => $data['nik'],
            ':phone'   => $data['phone'],
            ':address' => $data['address'],
            ':role'    => $data['role']   ?? 'petani',
            ':id'      => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $db   = Database::connect();
        $stmt = $db->prepare("
            UPDATE frm_farmers
            SET deleted_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        return $stmt->execute([':id' => $id]);
    }

    public function nikExists(string $nik, ?int $excludeId = null): bool
    {
        $db = Database::connect();

        if ($excludeId === null) {
            $stmt = $db->prepare("
                SELECT id FROM frm_farmers
                WHERE nik = :nik AND deleted_at IS NULL
            ");
            $stmt->execute([':nik' => $nik]);
        } else {
            $stmt = $db->prepare("
                SELECT id FROM frm_farmers
                WHERE nik = :nik AND id != :id AND deleted_at IS NULL
            ");
            $stmt->execute([':nik' => $nik, ':id' => $excludeId]);
        }

        return (bool) $stmt->fetch();
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $db = Database::connect();

        if ($excludeId === null) {
            $stmt = $db->prepare("
                SELECT id FROM frm_farmers
                WHERE email = :email AND deleted_at IS NULL
            ");
            $stmt->execute([':email' => $email]);
        } else {
            $stmt = $db->prepare("
                SELECT id FROM frm_farmers
                WHERE email = :email AND id != :id AND deleted_at IS NULL
            ");
            $stmt->execute([':email' => $email, ':id' => $excludeId]);
        }

        return (bool) $stmt->fetch();
    }
}

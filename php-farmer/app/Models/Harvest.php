<?php

namespace App\Models;

require_once __DIR__ . '/../Services/Database.php';

use App\Services\Database;
use PDO;

class Harvest
{
    public function getAll(
    $landId = null,
    $startDate = null,
    $endDate = null
)
{
    $db = Database::connect();

    $sql = "
        SELECT *
        FROM frm_harvests
        WHERE 1=1
    ";

    $params = [];

    if ($landId) {
        $sql .= " AND land_id = ?";
        $params[] = $landId;
    }

    if ($startDate) {
        $sql .= " AND harvest_date >= ?";
        $params[] = $startDate;
    }

    if ($endDate) {
        $sql .= " AND harvest_date <= ?";
        $params[] = $endDate;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    public function getById($id)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT *
            FROM frm_harvests
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO frm_harvests
            (
                land_id,
                crop_type,
                yield_ton,
                harvest_date,
                notes
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['land_id'],
            $data['crop_type'],
            $data['yield_ton'],
            $data['harvest_date'],
            $data['notes'] ?? null
        ]);

        return $db->lastInsertId();
    }

    public function update($id, $data)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE frm_harvests
            SET
                land_id = ?,
                crop_type = ?,
                yield_ton = ?,
                harvest_date = ?,
                notes = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['land_id'],
            $data['crop_type'],
            $data['yield_ton'],
            $data['harvest_date'],
            $data['notes'] ?? null,
            $id
        ]);
    }

    public function delete($id)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM frm_harvests
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }
}
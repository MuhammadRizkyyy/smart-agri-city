<?php

namespace App\Models;

require_once __DIR__ . '/../Services/Database.php';

use App\Services\Database;
use PDO;

class Land
{
    public function getAll($farmerId = null)
    {
        $db = Database::connect();

        if ($farmerId) {

            $stmt = $db->prepare("
                SELECT *
                FROM frm_lands
                WHERE farmer_id = ?
            ");

            $stmt->execute([$farmerId]);

        } else {

            $stmt = $db->query("
                SELECT *
                FROM frm_lands
            ");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT *
            FROM frm_lands
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        $land = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$land) {
            return null;
        }

        $soilStmt = $db->prepare("
            SELECT id, ph, nitrogen, phosphorus, potassium, recorded_at
            FROM crp_soil_conditions
            WHERE land_id = ?
            ORDER BY recorded_at DESC
            LIMIT 1
        ");
        $soilStmt->execute([$id]);
        $land['latest_soil_condition'] = $soilStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $land;
    }

    public function create($data)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO frm_lands
            (
                farmer_id,
                zone_id,
                name,
                area_ha,
                soil_type,
                lat,
                lng
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['farmer_id'],
            $data['zone_id'] ?? null,
            $data['name'],
            $data['area_ha'],
            $data['soil_type'],
            $data['lat'],
            $data['lng']
        ]);

        return $db->lastInsertId();
    }

    public function update($id, $data)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE frm_lands
            SET
                farmer_id = ?,
                zone_id = ?,
                name = ?,
                area_ha = ?,
                soil_type = ?,
                lat = ?,
                lng = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['farmer_id'],
            $data['zone_id'] ?? null,
            $data['name'],
            $data['area_ha'],
            $data['soil_type'],
            $data['lat'],
            $data['lng'],
            $id
        ]);
    }

    public function delete($id)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM frm_lands
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }
}
<?php

namespace App\Models;

require_once __DIR__ . '/../Services/Database.php';

use App\Services\Database;
use PDO;

class Farmer
{
    public function getAll()
    {
        $db = Database::connect();

        $stmt = $db->query("
            SELECT
                id,
                name,
                nik,
                phone,
                address
            FROM frm_farmers
            LIMIT 10
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            SELECT
                id,
                name,
                nik,
                phone,
                address
            FROM frm_farmers
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO frm_farmers
            (
                name,
                nik,
                phone,
                address
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['nik'],
            $data['phone'],
            $data['address']
        ]);

        return $db->lastInsertId();
    }

    public function update($id, $data)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            UPDATE frm_farmers
            SET
                name = ?,
                nik = ?,
                phone = ?,
                address = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $data['name'],
            $data['nik'],
            $data['phone'],
            $data['address'],
            $id
        ]);
    }

    public function delete($id)
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            DELETE FROM frm_farmers
            WHERE id = ?
        ");

        return $stmt->execute([$id]);
    }
}
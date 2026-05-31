<?php

namespace App\Models;

require_once __DIR__ . '/../Services/Database.php';

use App\Services\Database;

class Farmer
{
    public function getAll()
    {
        $db = Database::connect();

        $stmt = $db->query("
            SELECT
                id,
                name,
                phone
            FROM frm_farmers
            LIMIT 10
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
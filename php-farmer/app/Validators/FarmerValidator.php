<?php

namespace App\Validators;

require_once __DIR__ . '/../Services/Database.php';

use App\Services\Database;
use PDO;

class FarmerValidator
{
    public static function validate($data, $id = null)
    {
        // Required fields
        if (
            empty($data['name']) ||
            empty($data['nik']) ||
            empty($data['phone']) ||
            empty($data['address'])
        ) {
            return false;
        }

        // Format phone harus diawali +62
        if (!preg_match('/^\+62[0-9]{8,15}$/', $data['phone'])) {
            return false;
        }

        // NIK harus 16 digit angka
        if (!preg_match('/^[0-9]{16}$/', $data['nik'])) {
            return false;
        }

        // NIK unique
        $db = Database::connect();

        if ($id === null) {

    // CREATE
    $stmt = $db->prepare("
        SELECT id
        FROM frm_farmers
        WHERE nik = ?
    ");

    $stmt->execute([$data['nik']]);

} else {

    // UPDATE
    $stmt = $db->prepare("
        SELECT id
        FROM frm_farmers
        WHERE nik = ?
        AND id != ?
    ");

    $stmt->execute([
        $data['nik'],
        $id
    ]);
}

if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    return false;
}

        return true;
    }
}
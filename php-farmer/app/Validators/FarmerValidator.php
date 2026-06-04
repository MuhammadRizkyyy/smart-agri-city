<?php

namespace App\Validators;

class FarmerValidator
{
    public static function validate(array $data, ?int $id = null): array
    {
        $errors = [];

        // name
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required.';
        } elseif (strlen($data['name']) > 100) {
            $errors['name'] = 'Name must not exceed 100 characters.';
        }

        // nik
        if (empty($data['nik'])) {
            $errors['nik'] = 'NIK is required.';
        } elseif (!preg_match('/^[0-9]{16}$/', $data['nik'])) {
            $errors['nik'] = 'NIK must be exactly 16 digits.';
        } else {
            require_once __DIR__ . '/../Models/Farmer.php';
            $farmer = new \App\Models\Farmer();
            if ($farmer->nikExists($data['nik'], $id)) {
                $errors['nik'] = 'NIK already registered.';
            }
        }

        // phone
        if (empty($data['phone'])) {
            $errors['phone'] = 'Phone is required.';
        } elseif (!preg_match('/^\+62[0-9]{8,15}$/', $data['phone'])) {
            $errors['phone'] = 'Phone must start with +62 and contain 8–15 digits after it.';
        }

        // address
        if (empty($data['address'])) {
            $errors['address'] = 'Address is required.';
        }

        // email (opsional, tapi jika ada harus valid & unik)
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format.';
            } else {
                require_once __DIR__ . '/../Models/Farmer.php';
                $farmer = $farmer ?? new \App\Models\Farmer();
                if ($farmer->emailExists($data['email'], $id)) {
                    $errors['email'] = 'Email already registered.';
                }
            }
        }

        return $errors;
    }
}

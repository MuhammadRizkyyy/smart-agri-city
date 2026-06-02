<?php
namespace App\Validators;

class InputValidator {
    
    // Validasi untuk Jadwal Tanam
    public static function validateCrop(array $data): array {
        $errors = [];
        if (empty($data['land_id'])) $errors[] = "Field 'land_id' is required";
        if (empty($data['crop_type'])) $errors[] = "Field 'crop_type' is required";
        if (empty($data['plant_date'])) $errors[] = "Field 'plant_date' is required";

        // Validasi format tanggal YYYY-MM-DD
        if (!empty($data['plant_date']) && !preg_match("/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/", $data['plant_date'])) {
            $errors[] = "Field 'plant_date' must be in YYYY-MM-DD format";
        }
        if (!empty($data['expected_harvest']) && !preg_match("/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/", $data['expected_harvest'])) {
            $errors[] = "Field 'expected_harvest' must be in YYYY-MM-DD format";
        }

        // Validasi crop_type
        $validCrops = ['padi', 'jagung', 'singkong', 'cabai', 'tomat', 'kedelai', 'tebu'];
        if (!empty($data['crop_type']) && !in_array(strtolower($data['crop_type']), $validCrops)) {
            $errors[] = "Field 'crop_type' must be one of: " . implode(', ', $validCrops);
        }

        return $errors;
    }

    // Validasi untuk Alert Hama/Penyakit
    public static function validateAlert(array $data): array {
        $errors = [];
        if (empty($data['zone_id'])) $errors[] = "Field 'zone_id' is required";
        if (empty($data['alert_type'])) $errors[] = "Field 'alert_type' is required";
        if (empty($data['severity'])) $errors[] = "Field 'severity' is required";
        
        $validSeverities = ['low', 'medium', 'high', 'critical'];
        if (!empty($data['severity']) && !in_array(strtolower($data['severity']), $validSeverities)) {
            $errors[] = "Severity must be one of: " . implode(', ', $validSeverities);
        }

        return $errors;
    }

    // Validasi untuk Rekomendasi
    public static function validateSoil(array $data): array {
        $errors = [];
        if (empty($data['land_id'])) $errors[] = "Field 'land_id' is required";
        
        if (isset($data['ph']) && ($data['ph'] < 0 || $data['ph'] > 14)) {
            $errors[] = "pH value must be between 0 and 14";
        }

        return $errors;
    }
}
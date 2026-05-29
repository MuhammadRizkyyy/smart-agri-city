<?php
namespace App\Validators;

class SensorValidator {
    public static function validate(array $data): array {
        // Mapping data dari JSON Node-RED dan memastikan format angkanya sesuai untuk Database
        return [
            'zone_id'      => $data['zone'] ?? 'unknown',
            'moisture'     => floatval($data['moisture'] ?? 0),
            'temperature'  => floatval($data['temperature'] ?? 0),
            'ph'           => floatval($data['ph'] ?? 0),
            'nitrogen'     => floatval($data['nitrogen'] ?? 0),
            'phosphorus'   => floatval($data['phosphorus'] ?? 0),
            'potassium'    => floatval($data['potassium'] ?? 0),
            'air_temp'     => floatval($data['air_temp'] ?? 0),
            'air_humidity' => floatval($data['air_humidity'] ?? 0),
            'light_lux'    => floatval($data['light_lux'] ?? 0),
        ];
    }
}
<?php
namespace App\Validators;

class SensorValidator {
    /**
     * Validasi dan normalisasi payload sensor dari Node-RED.
     * zone_id di-handle oleh SensorController (tidak dikembalikan di sini).
     */
    public static function validate(array $data): array {
        return [
            'moisture'     => floatval($data['moisture']     ?? 0),
            'temperature'  => floatval($data['temperature']  ?? 0),
            'ph'           => floatval($data['ph']           ?? 0),
            'nitrogen'     => floatval($data['nitrogen']     ?? 0),
            'phosphorus'   => floatval($data['phosphorus']   ?? 0),
            'potassium'    => floatval($data['potassium']    ?? 0),
            'air_temp'     => floatval($data['air_temp']     ?? $data['temperature'] ?? 0),
            'air_humidity' => floatval($data['air_humidity'] ?? $data['humidity']    ?? 0),
            'light_lux'    => floatval($data['light_lux']    ?? 0),
        ];
    }
}

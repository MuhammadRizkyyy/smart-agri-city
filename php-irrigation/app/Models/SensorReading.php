<?php
namespace App\Models;

class SensorReading {
    private \PDO $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function create(array $data): array {
        $query = "INSERT INTO sensor_readings 
                  (zone_id, moisture, temperature, ph, nitrogen, phosphorus, potassium, air_temp, air_humidity, light_lux) 
                  VALUES 
                  (:zone_id, :moisture, :temperature, :ph, :nitrogen, :phosphorus, :potassium, :air_temp, :air_humidity, :light_lux)";
        
        $stmt = $this->db->prepare($query);
        
        // Eksekusi query dengan data yang sudah di-bind
        $stmt->execute([
            ':zone_id'      => $data['zone_id'],
            ':moisture'     => $data['moisture'],
            ':temperature'  => $data['temperature'],
            ':ph'           => $data['ph'],
            ':nitrogen'     => $data['nitrogen'],
            ':phosphorus'   => $data['phosphorus'],
            ':potassium'    => $data['potassium'],
            ':air_temp'     => $data['air_temp'],
            ':air_humidity' => $data['air_humidity'],
            ':light_lux'    => $data['light_lux']
        ]);

        $data['id'] = $this->db->lastInsertId();
        $data['recorded_at'] = date('Y-m-d H:i:s');
        
        return $data;
    }
}
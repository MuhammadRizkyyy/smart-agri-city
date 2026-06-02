<?php
namespace App\Controllers;

use App\Models\SoilCondition;
use App\Validators\InputValidator;

class RecommendController {
    private SoilCondition $model;

    public function __construct() {
        $this->model = new SoilCondition();
    }

    // GET /soil-conditions
    public function index(array $queryParams): array {
        $land_id = $queryParams['land_id'] ?? null;
        return [
            "status" => "success",
            "code" => 200,
            "data" => $this->model->getAll($land_id)
        ];
    }

    // POST /soil-conditions
    public function store(array $data): array {
        
        $errors = InputValidator::validateSoil($data);
        
        if (!empty($errors)) {
            return [
                "status"  => "error", 
                "code"    => 400, 
                "message" => "Validation failed", 
                "errors"  => $errors
            ];
        }

        $record = $this->model->create($data);
        
        return [
            "status"  => "success",
            "code"    => 201,
            "message" => "Soil condition recorded",
            "data"    => $record
        ];
    }

    // POST /recommend
    public function recommend(array $data): array {
        $ph = floatval($data['ph'] ?? 0);
        $n  = floatval($data['nitrogen'] ?? 0);
        $p  = floatval($data['phosphorus'] ?? 0);
        $k  = floatval($data['potassium'] ?? 0);

        $recommendations = [];

        // Rule 1: Padi (pH 5.5 - 7.0)
        if ($ph >= 5.5 && $ph <= 7.0) {
            $notes = "Padi cocok dengan tingkat pH ini.";
            if ($n < 30) $notes .= " Namun, level Nitrogen rendah, butuh tambahan pupuk Urea.";
            $recommendations[] = ["crop" => "Padi", "suitability" => "High", "notes" => $notes];
        }

        // Rule 2: Jagung (pH 6.0 - 7.5)
        if ($ph >= 6.0 && $ph <= 7.5) {
            $notes = "Kondisi sangat ideal untuk menanam Jagung.";
            if ($p < 20 || $k < 20) $notes .= " Pastikan asupan Fosfor (P) dan Kalium (K) ditingkatkan.";
            $recommendations[] = ["crop" => "Jagung", "suitability" => "High", "notes" => $notes];
        }

        // Rule 3: Singkong (pH 5.0 - 6.5)
        if ($ph >= 5.0 && $ph <= 6.5) {
            $notes = "Singkong sangat toleran terhadap pH agak asam ini.";
            if ($k < 15) $notes .= " Tambahkan pupuk Kalium agar pembentukan umbi optimal.";
            $recommendations[] = ["crop" => "Singkong", "suitability" => "Medium to High", "notes" => $notes];
        }

        // Fallback jika tanah terlalu ekstrem
        if (empty($recommendations)) {
            $recommendations[] = [
                "crop" => "Tanaman Penutup Tanah (Cover Crop) / Legum", 
                "suitability" => "Low", 
                "notes" => "Kondisi pH ($ph) cukup ekstrem. Disarankan melakukan treatment tanah (misal pengapuran) sebelum menanam tanaman utama."
            ];
        }

        return [
            "status" => "success",
            "code"   => 200,
            "data"   => [
                "input_parameters" => ["ph" => $ph, "nitrogen" => $n, "phosphorus" => $p, "potassium" => $k],
                "recommendations"  => $recommendations
            ]
        ];
    }
}
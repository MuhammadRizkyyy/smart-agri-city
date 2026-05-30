<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\IrrigationController;

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Handle CORS preflight
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Endpoint: POST /sensor
if ($method === 'POST' && $uri === '/sensor') {
    
    $inputJSON = file_get_contents('php://input');
    $inputData = json_decode($inputJSON, true);

    if (!$inputData) {
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "Invalid JSON payload"
        ]);
        exit;
    }

    try {
        
        $controller = new IrrigationController();
        $response = $controller->storeReading($inputData);

        http_response_code($response['code']);
        echo json_encode($response);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Internal Server Error: " . $e->getMessage()
        ]);
    }

} 
// Endpoint: GET /health
elseif ($method === 'GET' && $uri === '/health') {
    http_response_code(200);
    echo json_encode(["status" => "OK", "service" => "php-irrigation"]);
} 
else {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Endpoint not found"]);
}
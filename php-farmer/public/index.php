<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../app/Controllers/HealthController.php';
require_once __DIR__ . '/../app/Controllers/FarmerController.php';

use App\Controllers\HealthController;
use App\Controllers\FarmerController;

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/health') {
    (new HealthController())->index();
    exit;
}

if ($uri === '/farmers' && $method === 'GET') {
    (new FarmerController())->index();
    exit;
}

if ($uri === '/farmers' && $method === 'POST') {
    (new FarmerController())->store();
    exit;
}

if (preg_match('#^/farmers/(\d+)$#', $uri, $matches)) {

    $id = $matches[1];

    if ($method === 'GET') {
        (new FarmerController())->show($id);
        exit;
    }

    if ($method === 'PUT') {
        (new FarmerController())->update($id);
        exit;
    }

    if ($method === 'DELETE') {
        (new FarmerController())->destroy($id);
        exit;
    }
}

http_response_code(404);

echo json_encode([
    "status" => "error",
    "message" => "Route not found"
]);
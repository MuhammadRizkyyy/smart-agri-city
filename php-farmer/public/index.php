<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../app/Controllers/HealthController.php';
require_once __DIR__ . '/../app/Controllers/FarmerController.php';

use App\Controllers\HealthController;
use App\Controllers\FarmerController;

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/health') {

    $controller = new HealthController();
    $controller->index();

    exit;
}

if ($uri === '/farmers') {

    $controller = new FarmerController();
    $controller->index();

    exit;
}

echo json_encode([
    "status" => "ok",
    "service" => "farmer-service"
]);

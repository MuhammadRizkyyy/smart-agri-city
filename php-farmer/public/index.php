<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../app/Controllers/HealthController.php';

use App\Controllers\HealthController;

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/health') {

    $controller = new HealthController();
    $controller->index();

    exit;
}

echo json_encode([
    "status" => "ok",
    "service" => "farmer-service"
]);
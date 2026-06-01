<?php

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../app/Controllers/HealthController.php';
require_once __DIR__ . '/../app/Controllers/FarmerController.php';
require_once __DIR__ . '/../app/Controllers/LandController.php';
require_once __DIR__ . '/../app/Controllers/HarvestController.php';

use App\Controllers\HealthController;
use App\Controllers\FarmerController;
use App\Controllers\LandController;
use App\Controllers\HarvestController;

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

if ($uri === '/lands' && $method === 'GET') {
    (new LandController())->index();
    exit;
}

if ($uri === '/lands' && $method === 'POST') {
    (new LandController())->store();
    exit;
}

if (preg_match('#^/lands/(\d+)$#', $uri, $matches)) {

    $id = $matches[1];

    if ($method === 'GET') {
        (new LandController())->show($id);
        exit;
    }

    if ($method === 'PUT') {
        (new LandController())->update($id);
        exit;
    }

    if ($method === 'DELETE') {
        (new LandController())->destroy($id);
        exit;
    }
}

if ($uri === '/harvests' && $method === 'GET') {
    (new HarvestController())->index();
    exit;
}

if ($uri === '/harvests' && $method === 'POST') {
    (new HarvestController())->store();
    exit;
}

if (preg_match('#^/harvests/(\d+)$#', $uri, $matches)) {

    $id = $matches[1];

    if ($method === 'GET') {
        (new HarvestController())->show($id);
        exit;
    }

    if ($method === 'PUT') {
        (new HarvestController())->update($id);
        exit;
    }

    if ($method === 'DELETE') {
        (new HarvestController())->destroy($id);
        exit;
    }
}

http_response_code(404);

echo json_encode([
    "status" => "error",
    "message" => "Route not found"
]);
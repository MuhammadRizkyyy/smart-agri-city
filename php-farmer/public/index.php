<?php

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../app/Controllers/HealthController.php';
require_once __DIR__ . '/../app/Controllers/FarmerController.php';
require_once __DIR__ . '/../app/Controllers/LandController.php';
require_once __DIR__ . '/../app/Controllers/HarvestController.php';
require_once __DIR__ . '/../app/Services/Database.php';

use App\Controllers\HealthController;
use App\Controllers\FarmerController;
use App\Controllers\LandController;
use App\Controllers\HarvestController;

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/health' && $method === 'GET') {
    (new HealthController())->index();
    exit;
}

if ($uri === '/metrics' && $method === 'GET') {
    header('Content-Type: text/plain');

    $db = \App\Services\Database::connect();

    echo "# HELP farmer_service_up Farmer Service status\n";
    echo "# TYPE farmer_service_up gauge\n";
    echo "farmer_service_up 1\n\n";

    // jumlah data sensor irigasi
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM irr_sensor_readings
    ");
    $irr = $stmt->fetch();

    echo "# HELP irr_sensor_readings Total irrigation sensor readings\n";
    echo "# TYPE irr_sensor_readings gauge\n";
    echo "irr_sensor_readings {$irr['total']}\n\n";

    // jumlah alert hama
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM crp_alerts
    ");
    $alerts = $stmt->fetch();

    echo "# HELP crp_alerts Total crop alerts\n";
    echo "# TYPE crp_alerts gauge\n";
    echo "crp_alerts {$alerts['total']}\n";

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

if (preg_match('#^/farmers/(\d+)$#', $uri, $m)) {
    $id = (int) $m[1];
    match ($method) {
        'GET'    => (new FarmerController())->show($id),
        'PUT'    => (new FarmerController())->update($id),
        'DELETE' => (new FarmerController())->destroy($id),
        default  => null,
    };
    exit;
}

// Lands 
if ($uri === '/lands' && $method === 'GET') {
    (new LandController())->index();
    exit;
}

if ($uri === '/lands' && $method === 'POST') {
    (new LandController())->store();
    exit;
}

if (preg_match('#^/lands/(\d+)$#', $uri, $m)) {
    $id = (int) $m[1];
    match ($method) {
        'GET'    => (new LandController())->show($id),
        'PUT'    => (new LandController())->update($id),
        'DELETE' => (new LandController())->destroy($id),
        default  => null,
    };
    exit;
}

// Harvests 
if ($uri === '/harvests' && $method === 'GET') {
    (new HarvestController())->index();
    exit;
}

if ($uri === '/harvests' && $method === 'POST') {
    (new HarvestController())->store();
    exit;
}

if (preg_match('#^/harvests/(\d+)$#', $uri, $m)) {
    $id = (int) $m[1];
    match ($method) {
        'GET'    => (new HarvestController())->show($id),
        'PUT'    => (new HarvestController())->update($id),
        'DELETE' => (new HarvestController())->destroy($id),
        default  => null,
    };
    exit;
}

http_response_code(404);
echo json_encode([
    'status'    => 'error',
    'code'      => 404,
    'data'      => null,
    'message'   => 'Route not found',
    'timestamp' => gmdate('Y-m-d\TH:i:s.000\Z'),
    'service'   => 'farmer-service',
], JSON_UNESCAPED_SLASHES);

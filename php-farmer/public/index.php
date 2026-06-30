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
    header('Content-Type: text/plain; version=0.0.4; charset=utf-8');

    $db = \App\Services\Database::connect();

    echo "# HELP farmer_service_up Farmer Service status (1=up, 0=down)\n";
    echo "# TYPE farmer_service_up gauge\n";
    echo "farmer_service_up 1\n\n";

    // Total irrigation sensor readings
    $stmt = $db->query("SELECT COUNT(*) as total FROM irr_sensor_readings");
    $irr  = $stmt->fetch(\PDO::FETCH_ASSOC);
    echo "# HELP irr_sensor_readings Total irrigation sensor readings stored\n";
    echo "# TYPE irr_sensor_readings gauge\n";
    echo "irr_sensor_readings {$irr['total']}\n\n";

    // Soil moisture per zone (Panel 1: Soil Moisture per Zona)
    $stmt = $db->query("
        SELECT zone_id, AVG(moisture) AS avg_moisture
        FROM irr_sensor_readings
        WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY zone_id
    ");
    $moistureRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "# HELP irr_sensor_moisture_percent Average soil moisture per zone (last 1h, %)\n";
    echo "# TYPE irr_sensor_moisture_percent gauge\n";
    foreach ($moistureRows as $row) {
        $zid = (int)($row['zone_id'] ?? 0);
        $val = round((float)($row['avg_moisture'] ?? 0), 2);
        echo "irr_sensor_moisture_percent{zone_id=\"{$zid}\"} {$val}\n";
    }
    echo "\n";

    // Total pest alerts (Panel 2: Pest Alert Count) 
    $stmt   = $db->query("SELECT COUNT(*) as total FROM crp_alerts");
    $alerts = $stmt->fetch(\PDO::FETCH_ASSOC);
    echo "# HELP crp_alerts Total crop alerts recorded\n";
    echo "# TYPE crp_alerts gauge\n";
    echo "crp_alerts {$alerts['total']}\n\n";

    // Registered farmers 
    $stmt    = $db->query("SELECT COUNT(*) as total FROM frm_farmers WHERE deleted_at IS NULL");
    $farmers = $stmt->fetch(\PDO::FETCH_ASSOC);
    echo "# HELP farmer_total Total registered farmers\n";
    echo "# TYPE farmer_total gauge\n";
    echo "farmer_total {$farmers['total']}\n\n";

    // Harvest records 
    $stmt     = $db->query("SELECT COUNT(*) as total FROM frm_harvests");
    $harvests = $stmt->fetch(\PDO::FETCH_ASSOC);
    echo "# HELP harvest_records_total Total harvest records\n";
    echo "# TYPE harvest_records_total gauge\n";
    echo "harvest_records_total {$harvests['total']}\n";

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

// GET /farmers/by-phone/{phone}
if (preg_match('#^/farmers/by-phone/(.+)$#', $uri, $m)) {
    $phone = $m[1];
    if ($method === 'GET') {
        (new FarmerController())->getByPhone($phone);
    }
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

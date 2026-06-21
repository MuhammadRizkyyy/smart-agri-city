<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
\App\Services\EnvLoader::load(dirname(__DIR__));

use App\Middleware\JWTAuthMiddleware;
use App\Controllers\SensorController;
use App\Controllers\IrrigationController;
use App\Controllers\ZoneController;
use App\Controllers\HealthController;

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ─── Helpers ────────────────────────────────────────────────────────────────

function jsonError(int $code, string $message, $data = null): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode([
        'status'    => 'error',
        'code'      => $code,
        'data'      => $data,
        'message'   => $message,
        'timestamp' => date('c'),
        'service'   => 'irrigation-service',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// ─── Path normalization ──────────────────────────────────────────────────────
// Gateway melakukan pathRewrite: '^/api/sensors' → '/sensors' dan
// '^/api/irrigation' → '/irrigation'. Service ini juga menerima langsung
// (tanpa gateway) dengan prefix /api/, maupun dari IoT tanpa prefix.

$path = rtrim($uri, '/');
if ($path === '') $path = '/';

// Hilangkan /index.php jika ada
if (str_contains($path, '/index.php')) {
    $path = substr($path, strpos($path, '/index.php') + 10);
    if ($path === '') $path = '/';
}

// ─── Health — tidak butuh auth ───────────────────────────────────────────────
if ($method === 'GET' && in_array($path, ['/health', '/api/health'])) {
    (new HealthController())->check();
    exit;
}

// ─── Metrics — tidak butuh auth ──────────────────────────────────────────────
if ($method === 'GET' && in_array($path, ['/metrics', '/api/metrics'])) {
    header('Content-Type: text/plain; version=0.0.4; charset=utf-8');

    try {
        $dbObj = new \App\Models\Database();
        $db = $dbObj->getConnection();
    } catch (\Exception $e) {
        $db = null;
    }

    // Service health 
    echo "# HELP irrigation_service_up Irrigation Service status (1=up, 0=down)\n";
    echo "# TYPE irrigation_service_up gauge\n";
    echo "irrigation_service_up 1\n\n";

    if ($db) {
        // Soil moisture per zone (Panel 1: Soil Moisture per Zona)
        $stmt = $db->query("
            SELECT zone_id, AVG(moisture) AS avg_moisture
            FROM irr_sensor_readings
            WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY zone_id
        ");
        $moistureRows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        echo "# HELP irr_sensor_moisture_percent Average soil moisture per zone (last 1h)\n";
        echo "# TYPE irr_sensor_moisture_percent gauge\n";
        foreach ($moistureRows as $row) {
            $zid = (int)($row['zone_id'] ?? 0);
            $val = round((float)($row['avg_moisture'] ?? 0), 2);
            echo "irr_sensor_moisture_percent{zone_id=\"{$zid}\"} {$val}\n";
        }
        echo "\n";

        // Total sensor readings
        $stmt = $db->query("SELECT COUNT(*) AS total FROM irr_sensor_readings");
        $tot  = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : ['total' => 0];
        echo "# HELP irr_sensor_readings_total Total sensor readings stored\n";
        echo "# TYPE irr_sensor_readings_total counter\n";
        echo "irr_sensor_readings_total {$tot['total']}\n\n";

        // Irrigation volume per zone (Panel 4: Irrigation Volume)
        $stmt = $db->query("
            SELECT zone_id, SUM(volume_liters) AS total_liters
            FROM irr_irrigation_logs
            WHERE DATE(created_at) = CURDATE()
            GROUP BY zone_id
        ");
        $volRows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        echo "# HELP irr_irrigation_volume_liters_total Total irrigation volume per zone today (liters)\n";
        echo "# TYPE irr_irrigation_volume_liters_total counter\n";
        foreach ($volRows as $row) {
            $zid = (int)($row['zone_id'] ?? 0);
            $vol = round((float)($row['total_liters'] ?? 0), 2);
            echo "irr_irrigation_volume_liters_total{zone_id=\"{$zid}\"} {$vol}\n";
        }
        echo "\n";

        // ── Active zones ──────────────────────────────────────────────────
        $stmt   = $db->query("SELECT COUNT(*) AS total FROM irr_zones WHERE status = 'active'");
        $zones  = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : ['total' => 0];
        echo "# HELP irr_zones_active Active irrigation zones\n";
        echo "# TYPE irr_zones_active gauge\n";
        echo "irr_zones_active {$zones['total']}\n";
    }

    exit;
}

if ($method === 'POST' && in_array($path, ['/sensor', '/iot/sensor', '/api/iot/sensor'])) {
    (new SensorController())->storeReading();
    exit;
}


$user = JWTAuthMiddleware::authenticate();
if (!$user) {
    jsonError(401, 'Unauthorized: Invalid or missing token');
}

if (str_starts_with($path, '/api/')) {
    $path = substr($path, 4);
}

// Router 

// Sensor routes
if ($path === '/sensors/readings' && $method === 'POST') {
    (new SensorController())->storeReading();
    exit;
}

if ($path === '/sensors/current' && $method === 'GET') {
    (new SensorController())->getCurrentReading();
    exit;
}

if ($path === '/sensors/history' && $method === 'GET') {
    (new SensorController())->getHistory();
    exit;
}

// GET /sensors/zone/{zone_id}/latest
if (preg_match('#^/sensors/zone/(\d+)/latest$#', $path, $m) && $method === 'GET') {
    $_GET['zone_id'] = $m[1];
    (new SensorController())->getCurrentReading();
    exit;
}

// GET /sensors/{id}
if (preg_match('#^/sensors/(\d+)$#', $path, $m) && $method === 'GET') {
    $ctrl = new SensorController();
    $ctrl->getReadingById((int)$m[1]);
    exit;
}

// GET /sensors
if ($path === '/sensors' && $method === 'GET') {
    (new SensorController())->getHistory();
    exit;
}

// Irrigation routes
if ($path === '/irrigation/status' && $method === 'GET') {
    (new IrrigationController())->getStatus();
    exit;
}

if ($path === '/irrigation/command' && $method === 'POST') {
    $role = $user['role'] ?? null;
    if (!in_array($role, ['admin', 'petugas', 'petani'])) {
        jsonError(403, 'Forbidden: Insufficient privileges');
    }
    (new IrrigationController())->handleCommand();
    exit;
}

if ($path === '/irrigation/logs' && $method === 'GET') {
    (new IrrigationController())->getLogs();
    exit;
}

// GET /irrigation
if ($path === '/irrigation' && $method === 'GET') {
    (new IrrigationController())->getLogs();
    exit;
}

// POST /irrigation
if ($path === '/irrigation' && $method === 'POST') {
    $role = $user['role'] ?? null;
    if (!in_array($role, ['admin', 'petugas', 'petani'])) {
        jsonError(403, 'Forbidden: Insufficient privileges');
    }
    (new IrrigationController())->handleCommand();
    exit;
}

// PUT /irrigation/{id}
if (preg_match('#^/irrigation/(\d+)$#', $path, $m) && $method === 'PUT') {
    (new IrrigationController())->updateLog((int)$m[1]);
    exit;
}

// Zone routes
if ($path === '/zones' && $method === 'GET') {
    (new ZoneController())->index();
    exit;
}

if ($path === '/zones' && $method === 'POST') {
    $role = $user['role'] ?? null;
    if (!in_array($role, ['admin', 'petugas'])) {
        jsonError(403, 'Forbidden: Only admin or petugas can create zones');
    }
    (new ZoneController())->store();
    exit;
}

if (preg_match('#^/zones/(\d+)$#', $path, $m) && $method === 'GET') {
    (new ZoneController())->show((int)$m[1]);
    exit;
}

if (preg_match('#^/zones/(\d+)$#', $path, $m) && $method === 'PUT') {
    $role = $user['role'] ?? null;
    if (!in_array($role, ['admin', 'petugas'])) {
        jsonError(403, 'Forbidden: Only admin or petugas can update zones');
    }
    (new ZoneController())->update((int)$m[1]);
    exit;
}

jsonError(404, "Endpoint not found: {$method} {$path}");

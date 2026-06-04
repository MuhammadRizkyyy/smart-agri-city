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

// ─── IoT Sensor endpoint — OAuth client_credentials (sudah diverifikasi Gateway) ──
// Gateway strip prefix /iot sebelum forward, jadi sampai di sini sebagai /sensor
if ($method === 'POST' && in_array($path, ['/sensor', '/iot/sensor', '/api/iot/sensor'])) {
    (new SensorController())->storeReading();
    exit;
}

// ─── Auth untuk semua route lainnya ─────────────────────────────────────────
// Support dua pola path:
//   1. Langsung: /sensors/*, /irrigation/*, /zones/*        (sudah direwrite Gateway)
//   2. Dengan prefix: /api/sensors/*, /api/irrigation/*, /api/zones/*  (direct call)

$user = JWTAuthMiddleware::authenticate();
if (!$user) {
    jsonError(401, 'Unauthorized: Invalid or missing token');
}

// Normalisasi: strip /api prefix agar routing terpusat
if (str_starts_with($path, '/api/')) {
    $path = substr($path, 4); // '/api/...' → '/...'
}

// ─── Router ─────────────────────────────────────────────────────────────────

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

// GET /sensors/zone/{zone_id}/latest — data terkini per zona
if (preg_match('#^/sensors/zone/(\d+)/latest$#', $path, $m) && $method === 'GET') {
    $_GET['zone_id'] = $m[1];
    (new SensorController())->getCurrentReading();
    exit;
}

// GET /sensors/{id} — detail satu reading
if (preg_match('#^/sensors/(\d+)$#', $path, $m) && $method === 'GET') {
    // Forward ke list dengan ID filter — tambahkan method getById ke SensorController jika diperlukan
    // Untuk sementara redirect ke history dengan id param
    $ctrl = new SensorController();
    $ctrl->getReadingById((int)$m[1]);
    exit;
}

// GET /sensors — list readings (alias ke history)
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

// GET /irrigation — alias ke logs
if ($path === '/irrigation' && $method === 'GET') {
    (new IrrigationController())->getLogs();
    exit;
}

// POST /irrigation — log irigasi manual
if ($path === '/irrigation' && $method === 'POST') {
    $role = $user['role'] ?? null;
    if (!in_array($role, ['admin', 'petugas', 'petani'])) {
        jsonError(403, 'Forbidden: Insufficient privileges');
    }
    (new IrrigationController())->handleCommand();
    exit;
}

// PUT /irrigation/{id} — update status
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

// ─── 404 ─────────────────────────────────────────────────────────────────────
jsonError(404, "Endpoint not found: {$method} {$path}");

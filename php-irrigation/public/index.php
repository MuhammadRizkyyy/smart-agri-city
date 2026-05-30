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
use App\Controllers\HealthController;

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

function routeNotFound() {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'code' => 404,
        'data' => null,
        'message' => 'Endpoint not found',
        'timestamp' => date('c'),
        'service' => 'irrigation-service'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function routeUnauthorized(string $message = 'Unauthorized: Invalid or missing token') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'code' => 401,
        'data' => null,
        'message' => $message,
        'timestamp' => date('c'),
        'service' => 'irrigation-service'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function routeForbidden(string $message = 'Forbidden: Insufficient privileges') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'code' => 403,
        'data' => null,
        'message' => $message,
        'timestamp' => date('c'),
        'service' => 'irrigation-service'
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$cleanPath = $uri;
if (strpos($uri, '/index.php') !== false) {
    $cleanPath = substr($uri, strpos($uri, '/index.php') + 10);
}
$cleanPath = rtrim($cleanPath, '/');
if ($cleanPath === '') {
    $cleanPath = '/';
}

if ($method === 'GET' && ($cleanPath === '/health' || $cleanPath === '/api/health')) {
    $controller = new HealthController();
    $controller->check();
    exit;
}

if ($method === 'POST' && ($cleanPath === '/sensor' || $cleanPath === '/iot/sensor' || $cleanPath === '/api/iot/sensor')) {
    $controller = new SensorController();
    $controller->storeReading();
    exit;
}

$user = null;
if (str_starts_with($cleanPath, '/api/')) {
    $user = JWTAuthMiddleware::authenticate();
    if (!$user) {
        routeUnauthorized();
    }
} else {
    routeNotFound();
}

switch ($cleanPath) {
    case '/api/sensors/readings':
        if ($method === 'POST') {
            $controller = new SensorController();
            $controller->storeReading();
        } else {
            routeNotFound();
        }
        break;

    case '/api/sensors/current':
        if ($method === 'GET') {
            $controller = new SensorController();
            $controller->getCurrentReading();
        } else {
            routeNotFound();
        }
        break;

    case '/api/sensors/history':
        if ($method === 'GET') {
            $controller = new SensorController();
            $controller->getHistory();
        } else {
            routeNotFound();
        }
        break;

    case '/api/irrigation/status':
        if ($method === 'GET') {
            $controller = new IrrigationController();
            $controller->getStatus();
        } else {
            routeNotFound();
        }
        break;

    case '/api/irrigation/command':
        if ($method === 'POST') {
            $role = $user['role'] ?? null;
            if ($role !== 'admin' && $role !== 'petugas' && $role !== 'petani') {
                routeForbidden();
            }
            $controller = new IrrigationController();
            $controller->handleCommand();
        } else {
            routeNotFound();
        }
        break;

    case '/api/irrigation/logs':
        if ($method === 'GET') {
            $controller = new IrrigationController();
            $controller->getLogs();
        } else {
            routeNotFound();
        }
        break;

    default:
        routeNotFound();
        break;
}

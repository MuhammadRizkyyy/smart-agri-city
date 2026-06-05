<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\CropController;
use App\Controllers\AlertController;
use App\Controllers\RecommendController;

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Handle CORS preflight
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$inputJSON = file_get_contents('php://input');
$inputData = json_decode($inputJSON, true) ?? [];
$queryParams = $_GET;

function sendResponse(array $response): void {
    http_response_code($response['code'] ?? 500);
    echo json_encode($response);
    exit;
}

try {
    // HEALTH CHECK
    if ($method === 'GET' && $uri === '/health') {
        $dbStatus = 'ok';
        $dbError  = null;
        try {
            $db = (new \App\Models\Database())->getConnection();
            $db->query('SELECT 1');
        } catch (\Exception $e) {
            $dbStatus = 'error';
            $dbError  = $e->getMessage();
        }
        $code = $dbStatus === 'ok' ? 200 : 503;
        sendResponse([
            "status"    => $dbStatus === 'ok' ? "OK" : "error",
            "code"      => $code,
            "service"   => "php-crop",
            "database"  => $dbStatus,
            "timestamp" => date('Y-m-d\TH:i:s\Z'),
            "error"     => $dbError
        ]);
    }

    // METRICS
    if ($method === 'GET' && $uri === '/metrics') {
        header('Content-Type: text/plain');

        echo "# HELP crop_service_up Crop Service status\n";
        echo "# TYPE crop_service_up gauge\n";
        echo "crop_service_up 1\n";

        exit;
    }

    // ROUTING CROP SCHEDULE
    $cropController = new CropController();
    if (preg_match('#^/crops$#', $uri)) {
        if ($method === 'GET') sendResponse($cropController->index($queryParams));
        if ($method === 'POST') sendResponse($cropController->store($inputData));
    }
    if (preg_match('#^/crops/([0-9]+)$#', $uri, $matches)) {
        $id = (int) $matches[1];
        if ($method === 'GET') sendResponse($cropController->show($id));
        if ($method === 'PUT') sendResponse($cropController->update($id, $inputData));
        if ($method === 'DELETE') sendResponse($cropController->destroy($id));
    }

    // ROUTING ALERTS
    $alertController = new AlertController();
    if (preg_match('#^/alerts$#', $uri)) {
        if ($method === 'GET') sendResponse($alertController->index($queryParams));
        if ($method === 'POST') sendResponse($alertController->store($inputData));
    }
    if (preg_match('#^/alerts/([0-9]+)$#', $uri, $matches)) {
        $id = (int) $matches[1];
        if ($method === 'GET') sendResponse($alertController->show($id));
    }
    if (preg_match('#^/alerts/([0-9]+)/resolve$#', $uri, $matches)) {
        $id = (int) $matches[1];
        if ($method === 'PUT') sendResponse($alertController->resolve($id));
    }

    // ROUTING SOIL CONDITIONS & RECOMMENDATION
    $recommendController = new RecommendController();
    if (preg_match('#^/soil-conditions$#', $uri)) {
        if ($method === 'GET') sendResponse($recommendController->index($queryParams));
        if ($method === 'POST') sendResponse($recommendController->store($inputData));
    }
    if (preg_match('#^/recommend$#', $uri)) {
        if ($method === 'POST') sendResponse($recommendController->recommend($inputData));
    }

    // 404 FALLBACK
    sendResponse(["status" => "error", "code" => 404, "message" => "Endpoint not found"]);

} catch (\Exception $e) {
    error_log($e->getMessage());
    sendResponse([
        "status"  => "error",
        "code"    => 500,
        "message" => "Internal Server Error"
    ]);
}
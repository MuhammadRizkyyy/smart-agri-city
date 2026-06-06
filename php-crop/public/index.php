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
        header('Content-Type: text/plain; version=0.0.4; charset=utf-8');

        $db = null;
        $dbOk = false;
        try {
            $db    = (new \App\Models\Database())->getConnection();
            $dbOk  = true;
        } catch (\Exception $e) {}

        echo "# HELP crop_service_up Crop Service status (1=up, 0=down)\n";
        echo "# TYPE crop_service_up gauge\n";
        echo "crop_service_up 1\n\n";

        if ($db) {
            // Pest alerts per type (Panel 2: Pest Alert Count) 
            $stmt = $db->query("
                SELECT alert_type, COUNT(*) AS total
                FROM crp_alerts
                GROUP BY alert_type
            ");
            $alertRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo "# HELP crp_pest_alerts_total Total pest alerts per type\n";
            echo "# TYPE crp_pest_alerts_total counter\n";
            foreach ($alertRows as $row) {
                $t = addslashes($row['alert_type'] ?? 'unknown');
                echo "crp_pest_alerts_total{pest_type=\"{$t}\"} {$row['total']}\n";
            }

            // Total alerts (all time) 
            $stmt   = $db->query("SELECT COUNT(*) AS total FROM crp_alerts");
            $alerts = $stmt->fetch(\PDO::FETCH_ASSOC);
            echo "\n# HELP crp_alerts Total crop alerts recorded\n";
            echo "# TYPE crp_alerts gauge\n";
            echo "crp_alerts {$alerts['total']}\n\n";

            // Unresolved alerts (resolved_at IS NULL)
            $stmt   = $db->query("SELECT COUNT(*) AS total FROM crp_alerts WHERE resolved_at IS NULL");
            $active = $stmt->fetch(\PDO::FETCH_ASSOC);
            echo "# HELP crp_alerts_active Unresolved crop alerts (resolved_at IS NULL)\n";
            echo "# TYPE crp_alerts_active gauge\n";
            echo "crp_alerts_active {$active['total']}\n\n";

            // Crop schedules count 
            $stmt  = $db->query("SELECT COUNT(*) AS total FROM crp_crop_schedules");
            $crops = $stmt->fetch(\PDO::FETCH_ASSOC);
            echo "# HELP crp_schedules_total Total crop schedules\n";
            echo "# TYPE crp_schedules_total gauge\n";
            echo "crp_schedules_total {$crops['total']}\n\n";

            // Soil conditions count 
            $stmt = $db->query("SELECT COUNT(*) AS total FROM crp_soil_conditions");
            $soil = $stmt->fetch(\PDO::FETCH_ASSOC);
            echo "# HELP crp_soil_conditions_total Total soil condition records\n";
            echo "# TYPE crp_soil_conditions_total gauge\n";
            echo "crp_soil_conditions_total {$soil['total']}\n";
        }

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
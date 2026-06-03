<?php
namespace App\Controllers;

use App\Models\Database;

class HealthController extends BaseController {
    public function check(): void {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->query("SELECT 1");
            $stmt->execute();

            $this->success([
                'database' => 'connected',
                'service' => 'healthy'
            ], 'Service and database connection are healthy');
        } catch (\Exception $e) {
            $this->error('Service is unhealthy: ' . $e->getMessage(), 500, [
                'database' => 'disconnected'
            ]);
        }
    }
}

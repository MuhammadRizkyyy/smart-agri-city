<?php

namespace App\Controllers;

require_once __DIR__ . '/../Services/Response.php';
require_once __DIR__ . '/../Services/Database.php';

use App\Services\Response;
use App\Services\Database;

class HealthController
{
    public function index(): void
    {
        try {
            $db = Database::connect();
            $db->query('SELECT 1');

            Response::json(
                ['db' => 'connected'],
                'farmer-service healthy'
            );
        } catch (\Throwable $e) {
            Response::json(
                ['db' => 'disconnected', 'detail' => $e->getMessage()],
                'Database connection failed',
                500
            );
        }
    }
}

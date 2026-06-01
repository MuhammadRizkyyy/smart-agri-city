<?php

namespace App\Controllers;

require_once __DIR__ . '/../Services/Response.php';
require_once __DIR__ . '/../Services/Database.php';

use App\Services\Response;
use App\Services\Database;

class HealthController
{
    public function index()
    {
        try {

            Database::connect();

            Response::json(
                [
                    "db" => "connected"
                ],
                "farmer-service healthy"
            );

        } catch (\Exception $e) {

            Response::json(
                [
                    "db" => "disconnected"
                ],
                "database connection failed",
                500
            );
        }
    }
}
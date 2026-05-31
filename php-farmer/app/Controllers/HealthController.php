<?php

namespace App\Controllers;

require_once __DIR__ . '/../Services/Response.php';

use App\Services\Response;

class HealthController
{
    public function index()
    {
        Response::json(
            [
                "db" => "connected"
            ],
            "farmer-service healthy"
        );
    }
}
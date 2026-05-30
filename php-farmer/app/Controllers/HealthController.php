<?php

namespace App\Controllers;

class HealthController
{
    public function index()
    {
        echo json_encode([
            "status" => "success",
            "message" => "farmer-service healthy",
            "service" => "farmer-service"
        ]);
    }
}
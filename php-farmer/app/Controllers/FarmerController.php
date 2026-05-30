<?php

namespace App\Controllers;

class FarmerController
{
    public function index()
    {
        echo json_encode([
            [
                "id" => 1,
                "name" => "Windah basudara",
                "phone" => "08216471881"
            ]
        ]);
    }
}
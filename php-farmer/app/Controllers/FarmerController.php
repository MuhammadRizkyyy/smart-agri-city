<?php

namespace App\Controllers;

require_once __DIR__ . '/../Models/Farmer.php';

use App\Models\Farmer;

class FarmerController
{
    public function index()
    {
        $farmer = new Farmer();

        echo json_encode(
            $farmer->getAll()
        );
    }
}
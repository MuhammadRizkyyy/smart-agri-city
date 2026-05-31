<?php

namespace App\Controllers;

require_once __DIR__ . '/../Models/Farmer.php';
require_once __DIR__ . '/../Services/Response.php';

use App\Models\Farmer;
use App\Services\Response;

class FarmerController
{
    public function index()
    {
        $farmer = new Farmer();

        Response::json(
            $farmer->getAll(),
            "Farmer list retrieved"
        );
    }

    public function show($id)
    {
        $farmer = new Farmer();

        $data = $farmer->getById($id);

        if (!$data) {
            Response::json(
                null,
                "Farmer not found",
                404
            );
            return;
        }

        Response::json(
            $data,
            "Farmer detail retrieved"
        );
    }

    public function store()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        $farmer = new Farmer();

        $id = $farmer->create($input);

        Response::json(
            [
                "id" => $id
            ],
            "Farmer created",
            201
        );
    }

    public function update($id)
    {
        $input = json_decode(file_get_contents("php://input"), true);

        $farmer = new Farmer();

        $farmer->update($id, $input);

        Response::json(
            null,
            "Farmer updated"
        );
    }

    public function destroy($id)
    {
        $farmer = new Farmer();

        $farmer->delete($id);

        Response::json(
            null,
            "Farmer deleted"
        );
    }
}
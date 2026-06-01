<?php

namespace App\Controllers;

require_once __DIR__ . '/../Models/Land.php';
require_once __DIR__ . '/../Services/Response.php';
require_once __DIR__ . '/../Validators/LandValidator.php';
require_once __DIR__ . '/../Services/RabbitMQPublisher.php';

use App\Models\Land;
use App\Services\Response;
use App\Validators\LandValidator;
use App\Services\RabbitMQPublisher;

class LandController
{
    public function index()
    {
        $farmerId = $_GET['farmer_id'] ?? null;

        $land = new Land();

        Response::json(
            $land->getAll($farmerId),
            "Land list retrieved"
        );
    }

    public function show($id)
    {
        $land = new Land();

        $data = $land->getById($id);

        if (!$data) {

            Response::json(
                null,
                "Land not found",
                404
            );

            return;
        }

        Response::json(
            $data,
            "Land detail retrieved"
        );
    }

    public function store()
    {
        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!LandValidator::validate($input)) {

            Response::json(
                null,
                "Validation failed",
                422
            );

            return;
        }

        $land = new Land();

        $id = $land->create($input);

        $publisher = new RabbitMQPublisher();

        $publisher->publish(
            'land.created',
            [
                'id' => $id,
                'farmer_id' => $input['farmer_id'],
                'name' => $input['name']
            ]
        );

        Response::json(
            ["id" => $id],
            "Land created",
            201
        );
    }

    public function update($id)
    {
        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!LandValidator::validate($input)) {

            Response::json(
                null,
                "Validation failed",
                422
            );

            return;
        }

        $land = new Land();

        $land->update($id, $input);

        Response::json(
            null,
            "Land updated"
        );
    }

    public function destroy($id)
    {
        $land = new Land();

        $land->delete($id);

        Response::json(
            null,
            "Land deleted"
        );
    }
}
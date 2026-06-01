<?php

namespace App\Controllers;

require_once __DIR__ . '/../Models/Harvest.php';
require_once __DIR__ . '/../Services/Response.php';
require_once __DIR__ . '/../Validators/HarvestValidator.php';
require_once __DIR__ . '/../Services/RabbitMQPublisher.php';

use App\Models\Harvest;
use App\Services\Response;
use App\Validators\HarvestValidator;
use App\Services\RabbitMQPublisher;

class HarvestController
{
    public function index()
{
    $landId = $_GET['land_id'] ?? null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;

    $harvest = new Harvest();

    Response::json(
        $harvest->getAll(
            $landId,
            $startDate,
            $endDate
        ),
        "Harvest list retrieved"
    );
}

    public function show($id)
    {
        $harvest = new Harvest();

        $data = $harvest->getById($id);

        if (!$data) {

            Response::json(
                null,
                "Harvest not found",
                404
            );

            return;
        }

        Response::json(
            $data,
            "Harvest detail retrieved"
        );
    }

    public function store()
    {
        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!HarvestValidator::validate($input)) {

            Response::json(
                null,
                "Validation failed",
                422
            );

            return;
        }

        $harvest = new Harvest();

        $id = $harvest->create($input);

        $publisher = new RabbitMQPublisher();

        $publisher->publish(
        'harvest.recorded',
        [
            'id' => $id,
            'land_id' => $input['land_id']
        ]
    );

        Response::json(
            ["id" => $id],
            "Harvest created",
            201
        );
    }

    public function update($id)
    {
        $input = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!HarvestValidator::validate($input)) {

            Response::json(
                null,
                "Validation failed",
                422
            );

            return;
        }

        $harvest = new Harvest();

        $harvest->update($id, $input);

        Response::json(
            null,
            "Harvest updated"
        );
    }

    public function destroy($id)
    {
        $harvest = new Harvest();

        $harvest->delete($id);

        Response::json(
            null,
            "Harvest deleted"
        );
    }
}
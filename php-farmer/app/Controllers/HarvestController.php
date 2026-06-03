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
    // GET /harvests?land_id=1&start_date=2026-01-01&end_date=2026-12-31
    public function index(): void
    {
        $landId    = isset($_GET['land_id'])    ? (int)$_GET['land_id']    : null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate   = $_GET['end_date']   ?? null;

        $harvest = new Harvest();
        Response::json(
            $harvest->getAll($landId, $startDate, $endDate),
            'Harvest list retrieved'
        );
    }

    // GET /harvests/{id}
    public function show(int $id): void
    {
        $harvest = new Harvest();
        $data    = $harvest->getById($id);

        if (!$data) {
            Response::json(null, 'Harvest not found', 404);
            return;
        }

        Response::json($data, 'Harvest detail retrieved');
    }

    // POST /harvests
    public function store(): void
    {
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = HarvestValidator::validate($input);

        if (!empty($errors)) {
            Response::json(['errors' => $errors], 'Validation failed', 422);
            return;
        }

        $harvest = new Harvest();
        $id      = $harvest->create($input);

        try {
            $publisher = new RabbitMQPublisher();
            $publisher->publish('harvest.recorded', [
                'id'           => $id,
                'land_id'      => $input['land_id'],
                'crop_type'    => $input['crop_type'],
                'yield_ton'    => $input['yield_ton'],
                'harvest_date' => $input['harvest_date'],
            ]);
        } catch (\Throwable $e) {
            error_log('[RabbitMQ] harvest.recorded publish failed: ' . $e->getMessage());
        }

        Response::json(['id' => $id], 'Harvest created', 201);
    }

    // PUT /harvests/{id}
    public function update(int $id): void
    {
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = HarvestValidator::validate($input);

        if (!empty($errors)) {
            Response::json(['errors' => $errors], 'Validation failed', 422);
            return;
        }

        $harvest = new Harvest();

        if (!$harvest->getById($id)) {
            Response::json(null, 'Harvest not found', 404);
            return;
        }

        $harvest->update($id, $input);
        Response::json(null, 'Harvest updated');
    }

    // DELETE /harvests/{id}
    public function destroy(int $id): void
    {
        $harvest = new Harvest();

        if (!$harvest->getById($id)) {
            Response::json(null, 'Harvest not found', 404);
            return;
        }

        $harvest->delete($id);
        Response::json(null, 'Harvest deleted');
    }
}

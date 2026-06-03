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
    // GET /lands?farmer_id=1
    public function index(): void
    {
        $farmerId = isset($_GET['farmer_id']) ? (int)$_GET['farmer_id'] : null;

        $land = new Land();
        Response::json($land->getAll($farmerId), 'Land list retrieved');
    }

    // GET /lands/{id}
    public function show(int $id): void
    {
        $land = new Land();
        $data = $land->getById($id);

        if (!$data) {
            Response::json(null, 'Land not found', 404);
            return;
        }

        Response::json($data, 'Land detail retrieved');
    }

    // POST /lands
    public function store(): void
    {
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = LandValidator::validate($input);

        if (!empty($errors)) {
            Response::json(['errors' => $errors], 'Validation failed', 422);
            return;
        }

        $land = new Land();
        $id   = $land->create($input);

        try {
            $publisher = new RabbitMQPublisher();
            $publisher->publish('land.created', [
                'id'        => $id,
                'farmer_id' => $input['farmer_id'],
                'name'      => $input['name'],
                'area_ha'   => $input['area_ha'],
            ]);
        } catch (\Throwable $e) {
            error_log('[RabbitMQ] land.created publish failed: ' . $e->getMessage());
        }

        Response::json(['id' => $id], 'Land created', 201);
    }

    // PUT /lands/{id}
    public function update(int $id): void
    {
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = LandValidator::validate($input);

        if (!empty($errors)) {
            Response::json(['errors' => $errors], 'Validation failed', 422);
            return;
        }

        $land = new Land();

        if (!$land->getById($id)) {
            Response::json(null, 'Land not found', 404);
            return;
        }

        $land->update($id, $input);
        Response::json(null, 'Land updated');
    }

    // DELETE /lands/{id}
    public function destroy(int $id): void
    {
        $land = new Land();

        if (!$land->getById($id)) {
            Response::json(null, 'Land not found', 404);
            return;
        }

        $land->delete($id);
        Response::json(null, 'Land deleted');
    }
}

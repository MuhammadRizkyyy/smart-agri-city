<?php

namespace App\Controllers;

require_once __DIR__ . '/../Models/Farmer.php';
require_once __DIR__ . '/../Services/Response.php';
require_once __DIR__ . '/../Validators/FarmerValidator.php';
require_once __DIR__ . '/../Services/RabbitMQPublisher.php';

use App\Models\Farmer;
use App\Services\Response;
use App\Validators\FarmerValidator;
use App\Services\RabbitMQPublisher;

class FarmerController
{
    // GET /farmers?page=1&per_page=10
    public function index(): void
    {
        $page    = max(1, (int)($_GET['page']     ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 10)));

        $farmer = new Farmer();

        Response::json(
            $farmer->getAll($page, $perPage),
            'Farmer list retrieved'
        );
    }

    // GET /farmers/{id}
    public function show(int $id): void
    {
        $farmer = new Farmer();
        $data   = $farmer->getById($id);

        if (!$data) {
            Response::json(null, 'Farmer not found', 404);
            return;
        }

        Response::json($data, 'Farmer detail retrieved');
    }

    // POST /farmers
    public function store(): void
    {
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = FarmerValidator::validate($input);

        if (!empty($errors)) {
            Response::json(['errors' => $errors], 'Validation failed', 422);
            return;
        }

        $farmer = new Farmer();
        $id     = $farmer->create($input);

        try {
            $publisher = new RabbitMQPublisher();
            $publisher->publish('farmer.registered', [
                'id'    => $id,
                'name'  => $input['name'],
                'nik'   => $input['nik'],
            ]);
        } catch (\Throwable $e) {
            error_log('[RabbitMQ] farmer.registered publish failed: ' . $e->getMessage());
        }

        Response::json(['id' => $id], 'Farmer created', 201);
    }

    // PUT /farmers/{id}
    public function update(int $id): void
    {
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = FarmerValidator::validate($input, $id);

        if (!empty($errors)) {
            Response::json(['errors' => $errors], 'Validation failed', 422);
            return;
        }

        $farmer = new Farmer();

        if (!$farmer->getById($id)) {
            Response::json(null, 'Farmer not found', 404);
            return;
        }

        $farmer->update($id, $input);
        Response::json(null, 'Farmer updated');
    }

    // DELETE /farmers/{id}  — soft delete
    public function destroy(int $id): void
    {
        $farmer = new Farmer();

        if (!$farmer->getById($id)) {
            Response::json(null, 'Farmer not found', 404);
            return;
        }

        $farmer->delete($id);
        Response::json(null, 'Farmer deleted');
    }
}

<?php
namespace App\Controllers;

abstract class BaseController {
    protected function jsonResponse(string $status, int $code, $data = null, ?string $message = null): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Connection: keep-alive');
        http_response_code($code);
        
        $response = [
            'status' => $status,
            'code' => $code,
            'data' => $data,
            'message' => $message,
            'timestamp' => date('c'),
            'service' => 'irrigation-service'
        ];

        $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        header('Content-Length: ' . strlen($json));
        echo $json;
        flush();
        
        // Use exit without exit message to properly close connection
        exit(0);
    }

    protected function success($data = null, ?string $message = 'Success'): void {
        $this->jsonResponse('success', 200, $data, $message);
    }

    protected function created($data = null, ?string $message = 'Created'): void {
        $this->jsonResponse('success', 201, $data, $message);
    }

    protected function error(string $message, int $code = 400, $data = null): void {
        $this->jsonResponse('error', $code, $data, $message);
    }

    protected function unauthorized(string $message = 'Unauthorized'): void {
        $this->jsonResponse('error', 401, null, $message);
    }

    protected function forbidden(string $message = 'Forbidden'): void {
        $this->jsonResponse('error', 403, null, $message);
    }

    protected function notFound(string $message = 'Not Found'): void {
        $this->jsonResponse('error', 404, null, $message);
    }

    protected function getJsonBody(): ?array {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true);
    }
}

<?php

namespace App\Services;

class Response
{
    public static function json($data, $message = null, $code = 200)
    {
        http_response_code($code);

        echo json_encode([
            "status" => $code >= 400 ? "error" : "success",
            "code" => $code,
            "data" => $data,
            "message" => $message,
            "timestamp" => date('c'),
            "service" => "farmer-service"
        ]);
    }
}
<?php

namespace App\Services;

class Response
{
    public static function json($data, string $message = '', int $code = 200): void
    {
        http_response_code($code);

        echo json_encode([
            'status'    => $code >= 400 ? 'error' : 'success',
            'code'      => $code,
            'data'      => $data,
            'message'   => $message,
            'timestamp' => gmdate('Y-m-d\TH:i:s.000\Z'),
            'service'   => 'farmer-service',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

<?php
namespace App\Middleware;

class JWTAuthMiddleware {
    private static function base64UrlDecode(string $input): string {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    private static function base64UrlEncode(string $input): string {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    public static function authenticate(): ?array {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($headerB64, $payloadB64, $signatureB64) = $parts;

        $secret = getenv('JWT_SECRET') ?: 'super_secret_jwt_key_change_me_in_production_123456789';

        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true)
        );

        if (!hash_equals($expectedSignature, $signatureB64)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!$payload) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }
}

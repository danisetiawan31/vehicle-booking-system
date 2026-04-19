<?php
// app/Services/JwtService.php

declare(strict_types=1);

namespace App\Services;

class JwtService
{
    public function generate(array $payload): string
    {
        $secret = getenv('jwt.secret');
        if (!$secret) {
            $secret = ''; // Fallback or handle missing secret
        }

        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['exp'] = time() + 86400; // 24 hours
        $payloadJson = json_encode($payload);

        $base64UrlHeader = $this->base64urlEncode($header);
        $base64UrlPayload = $this->base64urlEncode($payloadJson);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = $this->base64urlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public function verify(string $token): array|false
    {
        $secret = getenv('jwt.secret');
        if (!$secret) {
            $secret = '';
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header64, $payload64, $signature64) = $parts;

        $validSignature = hash_hmac('sha256', $header64 . "." . $payload64, $secret, true);
        $validSignature64 = $this->base64urlEncode($validSignature);

        if (!hash_equals($validSignature64, $signature64)) {
            return false;
        }

        $payloadJson = $this->base64urlDecode($payload64);
        if ($payloadJson === false) {
            return false;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return false;
        }

        if (isset($payload['exp']) && time() >= $payload['exp']) {
            return false;
        }

        return $payload;
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string|false
    {
        $padding = strlen($data) % 4;
        $padded = $padding > 0 ? $data . str_repeat('=', 4 - $padding) : $data;
        return base64_decode(strtr($padded, '-_', '+/'), true);
    }
}

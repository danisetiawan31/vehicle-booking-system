<?php

use App\Services\JwtService;

beforeEach(function () {
    $this->originalSecret = getenv('jwt.secret');
});

afterEach(function () {
    if ($this->originalSecret !== false) {
        putenv("jwt.secret={$this->originalSecret}");
    } else {
        putenv('jwt.secret');
    }
});

test('generate and verify token with valid secret returns decoded payload', function () {
    putenv('jwt.secret=super-secret-key-123');

    $jwtService = new JwtService();
    $inputPayload = [
        'id'    => 1,
        'name'  => 'Admin Utama',
        'email' => 'admin@vehicle.com',
        'role'  => 'admin',
    ];

    $token = $jwtService->generate($inputPayload);
    expect($token)->toBeString();
    expect(count(explode('.', $token)))->toBe(3);

    $verifiedPayload = $jwtService->verify($token);
    expect($verifiedPayload)->toBeArray();
    expect($verifiedPayload['id'])->toBe(1);
    expect($verifiedPayload['name'])->toBe('Admin Utama');
    expect($verifiedPayload['email'])->toBe('admin@vehicle.com');
    expect($verifiedPayload['role'])->toBe('admin');
    expect($verifiedPayload['exp'])->toBeGreaterThan(time());
});

test('verify returns false when token is expired', function () {
    putenv('jwt.secret=super-secret-key-123');

    $jwtService = new JwtService();

    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = [
        'id'   => 1,
        'role' => 'admin',
        'exp'  => time() - 3600, // 1 hour ago
    ];

    $base64UrlHeader  = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $signature        = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, 'super-secret-key-123', true);
    $base64UrlSig     = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    $expiredToken = "{$base64UrlHeader}.{$base64UrlPayload}.{$base64UrlSig}";

    $result = $jwtService->verify($expiredToken);
    expect($result)->toBeFalse();
});

test('verify returns false when token is malformed', function () {
    putenv('jwt.secret=super-secret-key-123');

    $jwtService = new JwtService();

    expect($jwtService->verify('invalid.token'))->toBeFalse();
    expect($jwtService->verify('not-a-token-at-all'))->toBeFalse();
    expect($jwtService->verify('part1.part2.part3.part4'))->toBeFalse();
});

test('verify returns false when signature is tampered', function () {
    putenv('jwt.secret=super-secret-key-123');

    $jwtService = new JwtService();
    $token = $jwtService->generate(['id' => 1, 'role' => 'admin']);

    $parts = explode('.', $token);
    $lastChar = substr($parts[2], -1);
    $newChar = $lastChar === 'A' ? 'B' : 'A';
    $tamperedSignature = substr($parts[2], 0, -1) . $newChar;

    $tamperedToken = "{$parts[0]}.{$parts[1]}.{$tamperedSignature}";

    expect($jwtService->verify($tamperedToken))->toBeFalse();
});

test('generate throws RuntimeException when jwt secret is empty or not set', function () {
    putenv('jwt.secret='); // Empty env

    $jwtService = new JwtService();

    expect(fn () => $jwtService->generate(['id' => 1]))
        ->toThrow(RuntimeException::class, 'JWT secret key is not configured.');
});

test('verify throws RuntimeException when jwt secret is empty or not set', function () {
    putenv('jwt.secret=temporary-secret');
    $jwtService = new JwtService();
    $token = $jwtService->generate(['id' => 1]);

    putenv('jwt.secret='); // Empty env

    expect(fn () => $jwtService->verify($token))
        ->toThrow(RuntimeException::class, 'JWT secret key is not configured.');
});

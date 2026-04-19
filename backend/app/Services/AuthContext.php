<?php
// app/Services/AuthContext.php

declare(strict_types=1);

namespace App\Services;

class AuthContext
{
    private static array $payload = [];

    public static function set(array $payload): void
    {
        self::$payload = $payload;
    }

    public static function get(): array
    {
        return self::$payload;
    }
}

<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    public array $allowedOrigins = ['http://localhost:5173'];
    public array $allowedOriginsPatterns = [];
    public array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'];
    public array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
    public array $exposedHeaders = [];
    public int $maxAge = 7200;
    public bool $supportsCredentials = false;
}
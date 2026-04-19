<?php
// app/Filters/JwtAuthFilter.php

declare(strict_types=1);

namespace App\Filters;

use App\Services\JwtService;
use App\Services\AuthContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['status' => false, 'message' => 'Unauthorized', 'data' => null]);
        }

        $token = substr($authHeader, 7);

        $jwtService = new JwtService();
        $payload = $jwtService->verify($token);

        if ($payload === false) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED)
                ->setJSON(['status' => false, 'message' => 'Unauthorized', 'data' => null]);
        }

        AuthContext::set($payload);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
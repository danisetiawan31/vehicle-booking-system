<?php
// app/Controllers/Api/AuthController.php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Services\JwtService;
use App\Services\ActivityLogService;
use CodeIgniter\Controller;

class AuthController extends Controller
{
    public function login()
    {
        $json = $this->request->getJSON(true) ?? [];
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        $validation = service('validation');
        $validation->setRules($rules);

        if (!$validation->run($json)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'    => $validation->getErrors(),
                ]);
        }

        $model = new UserModel();
        $user = $model->where('email', $json['email'])->first();

        if (!$user) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Invalid credentials',
                    'data'    => null,
                ]);
        }

        if (!password_verify($json['password'], $user['password'])) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Invalid credentials',
                    'data'    => null,
                ]);
        }

        $jwtService = new JwtService();
        $token = $jwtService->generate([
            'id'             => $user['id'],
            'name'           => $user['name'],
            'email'          => $user['email'],
            'role'           => $user['role'],
            'approval_level' => $user['approval_level'],
        ]);

        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$user['id'],
            'user.login',
            'user',
            (int)$user['id'],
            "User {$user['name']} logged in",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Login successful',
                'data'    => [
                    'token' => $token,
                    'user'  => [
                        'id'             => $user['id'],
                        'name'           => $user['name'],
                        'email'          => $user['email'],
                        'role'           => $user['role'],
                        'approval_level' => $user['approval_level'],
                    ],
                ],
            ]);
    }

    public function logout()
    {
        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Logout successful',
                'data'    => null,
            ]);
    }
}
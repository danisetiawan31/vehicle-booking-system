<?php
// app/Controllers/Api/UserController.php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Services\ActivityLogService;
use App\Services\AuthContext;
use CodeIgniter\Controller;

class UserController extends Controller
{
    private function getAuthUser(): array
    {
        return AuthContext::get();
    }

    private function requireAdmin()
    {
        $user = $this->getAuthUser();
        if ($user['role'] !== 'admin') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Forbidden',
                    'data'    => null,
                ]);
        }
        return null;
    }

    public function index()
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $model = new UserModel();
        $users = $model->orderBy('created_at', 'DESC')->findAll();

        $users = array_map(function ($user) {
            unset($user['password']);
            return $user;
        }, $users);

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Users retrieved',
                'data'    => $users,
            ]);
    }

    public function create()
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $json = $this->request->getJSON(true) ?? [];

        $rules = [
            'name'     => 'required',
            'email'    => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[6]',
            'role'     => 'required|in_list[admin,approver]',
        ];

        $validation = service('validation');
        $validation->setRules($rules);

        if (!$validation->run($json)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => $validation->getErrors(),
                ]);
        }

        $role = $json['role'];
        $approvalLevel = null;

        if ($role === 'approver') {
            $level = $json['approval_level'] ?? null;
            if (!in_array((string)$level, ['1', '2'], true)) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status'  => false,
                        'message' => 'Validation failed',
                        'errors'  => ['approval_level' => 'Approval level must be 1 or 2 for approver.'],
                    ]);
            }
            $approvalLevel = (int)$level;
        } elseif (isset($json['approval_level']) && $json['approval_level'] !== null) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => ['approval_level' => 'Admin role must not have an approval level.'],
                ]);
        }

        $model = new UserModel();
        $model->insert([
            'name'           => $json['name'],
            'email'          => $json['email'],
            'password'       => password_hash($json['password'], PASSWORD_DEFAULT),
            'role'           => $role,
            'approval_level' => $approvalLevel,
        ]);

        $newId = $model->getInsertID();
        $newUser = $model->find($newId);
        unset($newUser['password']);

        $authUser = $this->getAuthUser();
        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'user.created',
            'user',
            (int)$newId,
            "Admin {$authUser['name']} created user {$json['name']}",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'status'  => true,
                'message' => 'User created',
                'data'    => $newUser,
            ]);
    }

    public function update($id)
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $model = new UserModel();
        $existing = $model->find($id);

        if (!$existing) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => false,
                    'message' => 'User not found',
                    'data'    => null,
                ]);
        }

        $json = $this->request->getJSON(true) ?? [];

        $rules = [];
        if (isset($json['email'])) {
            $rules['email'] = "valid_email|is_unique[users.email,id,{$id}]";
        }
        if (isset($json['role'])) {
            $rules['role'] = 'in_list[admin,approver]';
        }
        if (isset($json['password'])) {
            $rules['password'] = 'min_length[6]';
        }

        if (!empty($rules)) {
            $validation = service('validation');
            $validation->setRules($rules);
            if (!$validation->run($json)) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status'  => false,
                        'message' => 'Validation failed',
                        'errors'  => $validation->getErrors(),
                    ]);
            }
        }

        $updateData = [];
        if (isset($json['name']))  $updateData['name']  = $json['name'];
        if (isset($json['email'])) $updateData['email'] = $json['email'];
        if (isset($json['role']))  $updateData['role']  = $json['role'];
        if (isset($json['password'])) {
            $updateData['password'] = password_hash($json['password'], PASSWORD_DEFAULT);
        }

        $role = $json['role'] ?? $existing['role'];
        if (array_key_exists('approval_level', $json) || isset($json['role'])) {
            if ($role === 'approver') {
                $level = array_key_exists('approval_level', $json) ? $json['approval_level'] : $existing['approval_level'];
                
                if ($level === null) {
                    return $this->response
                        ->setStatusCode(422)
                        ->setJSON([
                            'status'  => false,
                            'message' => 'Validation failed',
                            'errors'  => ['approval_level' => 'Approval level is required for approver role.'],
                        ]);
                }

                if (!in_array((string)$level, ['1', '2'], true)) {
                    return $this->response
                        ->setStatusCode(422)
                        ->setJSON([
                            'status'  => false,
                            'message' => 'Validation failed',
                            'errors'  => ['approval_level' => 'Approval level must be 1 or 2 for approver.'],
                        ]);
                }
                $updateData['approval_level'] = (int)$level;
            } else {
                $updateData['approval_level'] = null;
            }
        }

        $model->update($id, $updateData);

        $updated = $model->find($id);
        unset($updated['password']);

        $authUser = $this->getAuthUser();
        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'user.updated',
            'user',
            (int)$id,
            "Admin {$authUser['name']} updated user ID {$id}",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'User updated',
                'data'    => $updated,
            ]);
    }

    public function delete($id)
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $authUser = $this->getAuthUser();

        if ((int)$id === (int)$authUser['id']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Cannot delete your own account',
                    'data'    => null,
                ]);
        }

        $model = new UserModel();
        $existing = $model->find($id);

        if (!$existing) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => false,
                    'message' => 'User not found',
                    'data'    => null,
                ]);
        }

        $model->delete($id);

        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'user.deleted',
            'user',
            (int)$id,
            "Admin {$authUser['name']} deleted user ID {$id}",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'User deleted',
                'data'    => null,
            ]);
    }
}

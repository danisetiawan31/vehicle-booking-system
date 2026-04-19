<?php
// app/Controllers/Api/DriverController.php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\DriverModel;
use App\Services\ActivityLogService;
use App\Services\AuthContext;
use CodeIgniter\Controller;

class DriverController extends Controller
{
    private function getAuthUser(): array
    {
        return AuthContext::get();
    }

    private function requireAdmin()
    {
        if ($this->getAuthUser()['role'] !== 'admin') {
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

        $model = new DriverModel();
        $builder = $model->orderBy('created_at', 'DESC');

        $status = $this->request->getGet('status');
        if ($status !== null) {
            $builder->where('status', $status);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Drivers retrieved',
                'data'    => $builder->findAll(),
            ]);
    }

    public function create()
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $json = $this->request->getJSON(true) ?? [];

        $validation = service('validation');
        $validation->setRules([
            'name'           => 'required',
            'license_number' => 'required',
            'phone'          => 'required',
            'status'         => 'permit_empty|in_list[active,inactive]',
        ]);

        if (!$validation->run($json)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'data'    => $validation->getErrors(),
                ]);
        }

        $model = new DriverModel();
        $model->insert([
            'name'           => $json['name'],
            'license_number' => $json['license_number'],
            'phone'          => $json['phone'],
            'status'         => $json['status'] ?? 'active',
        ]);

        $newId = $model->getInsertID();
        $driver = $model->find($newId);

        $authUser = $this->getAuthUser();
        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'driver.created',
            'driver',
            (int)$newId,
            "Admin {$authUser['name']} created driver {$json['name']}",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'status'  => true,
                'message' => 'Driver created',
                'data'    => $driver,
            ]);
    }

    public function update($id)
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $model = new DriverModel();
        $existing = $model->find($id);

        if (!$existing) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Driver not found',
                    'data'    => null,
                ]);
        }

        $json = $this->request->getJSON(true) ?? [];

        if (isset($json['status'])) {
            $validation = service('validation');
            $validation->setRules(['status' => 'in_list[active,inactive]']);
            if (!$validation->run($json)) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'status'  => false,
                        'message' => 'Validation failed',
                        'data'    => $validation->getErrors(),
                    ]);
            }
        }

        $updateData = array_intersect_key($json, array_flip([
            'name', 'license_number', 'phone', 'status',
        ]));

        $model->update($id, $updateData);

        $authUser = $this->getAuthUser();
        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'driver.updated',
            'driver',
            (int)$id,
            "Admin {$authUser['name']} updated driver ID {$id}",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Driver updated',
                'data'    => $model->find($id),
            ]);
    }

    public function delete($id)
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $model = new DriverModel();
        $existing = $model->find($id);

        if (!$existing) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Driver not found',
                    'data'    => null,
                ]);
        }

        $model->delete($id);

        $authUser = $this->getAuthUser();
        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'driver.deleted',
            'driver',
            (int)$id,
            "Admin {$authUser['name']} deleted driver ID {$id} ({$existing['name']})",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Driver deleted',
                'data'    => null,
            ]);
    }
}

<?php
// app/Controllers/Api/VehicleController.php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\VehicleModel;
use App\Services\ActivityLogService;
use App\Services\AuthContext;
use CodeIgniter\Controller;

class VehicleController extends Controller
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

        $model = new VehicleModel();
        $builder = $model->orderBy('created_at', 'DESC');

        $status = $this->request->getGet('status');
        if ($status !== null) {
            $builder->where('status', $status);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Vehicles retrieved',
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
            'name'         => 'required',
            'plate_number' => 'required|is_unique[vehicles.plate_number]',
            'type'         => 'required|in_list[passenger,cargo]',
            'ownership'    => 'required|in_list[own,rental]',
            'region'       => 'required',
            'status'       => 'permit_empty|in_list[available,maintenance,inactive]',
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

        $model = new VehicleModel();
        $model->insert([
            'name'         => $json['name'],
            'plate_number' => $json['plate_number'],
            'type'         => $json['type'],
            'ownership'    => $json['ownership'],
            'region'       => $json['region'],
            'status'       => $json['status'] ?? 'available',
        ]);

        $newId = $model->getInsertID();
        $vehicle = $model->find($newId);

        $authUser = $this->getAuthUser();
        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'vehicle.created',
            'vehicle',
            (int)$newId,
            "Admin {$authUser['name']} created vehicle {$json['name']} ({$json['plate_number']})",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'status'  => true,
                'message' => 'Vehicle created',
                'data'    => $vehicle,
            ]);
    }

    public function update($id)
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $model = new VehicleModel();
        $existing = $model->find($id);

        if (!$existing) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Vehicle not found',
                    'data'    => null,
                ]);
        }

        $json = $this->request->getJSON(true) ?? [];

        $rules = [];
        if (isset($json['plate_number'])) {
            $rules['plate_number'] = "is_unique[vehicles.plate_number,id,{$id}]";
        }
        if (isset($json['type'])) {
            $rules['type'] = 'in_list[passenger,cargo]';
        }
        if (isset($json['ownership'])) {
            $rules['ownership'] = 'in_list[own,rental]';
        }
        if (isset($json['status'])) {
            $rules['status'] = 'in_list[available,maintenance,inactive]';
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
                        'data'    => $validation->getErrors(),
                    ]);
            }
        }

        $updateData = array_intersect_key($json, array_flip([
            'name', 'plate_number', 'type', 'ownership', 'region', 'status',
        ]));

        $model->update($id, $updateData);

        $authUser = $this->getAuthUser();
        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'vehicle.updated',
            'vehicle',
            (int)$id,
            "Admin {$authUser['name']} updated vehicle ID {$id}",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Vehicle updated',
                'data'    => $model->find($id),
            ]);
    }

    public function delete($id)
    {
        $guard = $this->requireAdmin();
        if ($guard !== null) return $guard;

        $model = new VehicleModel();
        $existing = $model->find($id);

        if (!$existing) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Vehicle not found',
                    'data'    => null,
                ]);
        }

        $model->delete($id);

        $authUser = $this->getAuthUser();
        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'vehicle.deleted',
            'vehicle',
            (int)$id,
            "Admin {$authUser['name']} deleted vehicle ID {$id} ({$existing['plate_number']})",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Vehicle deleted',
                'data'    => null,
            ]);
    }
}

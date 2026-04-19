<?php
// app/Controllers/Api/ActivityLogController.php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\AuthContext;
use CodeIgniter\Controller;
use Config\Database;

class ActivityLogController extends Controller
{
    public function index()
    {
        if (AuthContext::get()['role'] !== 'admin') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['status' => false, 'message' => 'Forbidden', 'data' => null]);
        }

        $db = Database::connect();
        $builder = $db->table('activity_logs al')
            ->select('al.*, u.name as user_name')
            ->join('users u', 'u.id = al.user_id', 'left');

        $entityType = $this->request->getGet('entity_type');
        $startDate  = $this->request->getGet('start_date');
        $endDate    = $this->request->getGet('end_date');
        $page       = (int)($this->request->getGet('page') ?? 1);

        if ($page < 1) {
            $page = 1;
        }

        if (!empty($entityType)) {
            $builder->where('al.entity_type', $entityType);
        }

        if (!empty($startDate)) {
            $builder->where('al.created_at >=', $startDate . ' 00:00:00');
        }

        if (!empty($endDate)) {
            $builder->where('al.created_at <=', $endDate . ' 23:59:59');
        }

        // Count total matching records before applying limit/offset
        $total = $builder->countAllResults(false); // false means do not reset the query

        $limit = 50;
        $offset = ($page - 1) * $limit;
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        $logs = $builder->orderBy('al.created_at', 'DESC')
                        ->limit($limit, $offset)
                        ->get()
                        ->getResultArray();

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Success',
                'data'    => [
                    'logs' => $logs,
                    'pagination' => [
                        'total'       => $total,
                        'page'        => $page,
                        'limit'       => $limit,
                        'total_pages' => $totalPages,
                    ]
                ],
            ]);
    }
}

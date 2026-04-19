<?php
// app/Controllers/Api/DashboardController.php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\AuthContext;
use CodeIgniter\Controller;
use Config\Database;

class DashboardController extends Controller
{
    public function index()
    {
        $authUser = $this->getAuthUser();
        $db = Database::connect();

        if ($authUser['role'] === 'admin') {
            return $this->response
                ->setStatusCode(200)
                ->setJSON([
                    'status'  => true,
                    'message' => 'Dashboard data retrieved',
                    'data'    => $this->buildAdminDashboard($db),
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Dashboard data retrieved',
                'data'    => $this->buildApproverDashboard($db, (int)$authUser['id']),
            ]);
    }

    // -------------------------------------------------------------------------
    private function getAuthUser(): array
    {
        return AuthContext::get();
    }

    // -------------------------------------------------------------------------
    private function buildAdminDashboard($db): array
    {
        $now = date('Y-m-d H:i:s');
        $currentYear  = date('Y');
        $currentMonth = date('n');

        // Summary
        $totalVehicles = (int)$db->table('vehicles')
            ->where('status', 'available')
            ->countAllResults();

        $totalBookingsThisMonth = (int)$db->table('bookings')
            ->where('YEAR(created_at)', $currentYear)
            ->where('MONTH(created_at)', $currentMonth)
            ->countAllResults();

        $pendingApproval = (int)$db->table('bookings')
            ->whereIn('status', ['waiting_level_1', 'waiting_level_2'])
            ->countAllResults();

        $approvedThisMonth = (int)$db->table('bookings')
            ->where('status', 'approved')
            ->where('YEAR(created_at)', $currentYear)
            ->where('MONTH(created_at)', $currentMonth)
            ->countAllResults();

        // Bookings per month — last 12 months
        $twelveMonthsAgo = date('Y-m-d H:i:s', strtotime('-12 months', strtotime($now)));
        $bookingsPerMonth = $db->table('bookings')
            ->select('YEAR(created_at) AS year, MONTH(created_at) AS month, COUNT(*) AS total')
            ->where('created_at >=', $twelveMonthsAgo)
            ->groupBy('YEAR(created_at), MONTH(created_at)')
            ->orderBy('YEAR(created_at) ASC, MONTH(created_at) ASC')
            ->get()->getResultArray();

        // Status distribution
        $statusDistribution = $db->table('bookings')
            ->select('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()->getResultArray();

        // Top 5 vehicles (approved bookings only)
        $topVehicles = $db->table('bookings b')
            ->select('b.vehicle_id, v.name AS vehicle_name, v.plate_number, COUNT(*) AS total')
            ->join('vehicles v', 'v.id = b.vehicle_id')
            ->where('b.status', 'approved')
            ->groupBy('b.vehicle_id, v.name, v.plate_number')
            ->orderBy('total', 'DESC')
            ->limit(5)
            ->get()->getResultArray();

        return [
            'summary' => [
                'total_vehicles'          => $totalVehicles,
                'total_bookings_this_month' => $totalBookingsThisMonth,
                'pending_approval'        => $pendingApproval,
                'approved_this_month'     => $approvedThisMonth,
            ],
            'bookings_per_month'  => $bookingsPerMonth,
            'status_distribution' => $statusDistribution,
            'top_vehicles'        => $topVehicles,
        ];
    }

    // -------------------------------------------------------------------------
    private function buildApproverDashboard($db, int $approverId): array
    {
        $pendingForMe = (int)$db->table('booking_approvals')
            ->where('approver_id', $approverId)
            ->where('status', 'pending')
            ->countAllResults();

        $pendingBookings = $db->table('booking_approvals ba')
            ->select('b.id AS booking_id, b.booking_code, b.requester_name,
                      v.name AS vehicle_name, v.plate_number,
                      d.name AS driver_name,
                      b.start_date, b.end_date, b.status AS booking_status,
                      ba.level AS approval_level')
            ->join('bookings b', 'b.id = ba.booking_id')
            ->join('vehicles v', 'v.id = b.vehicle_id')
            ->join('drivers d', 'd.id = b.driver_id')
            ->where('ba.approver_id', $approverId)
            ->where('ba.status', 'pending')
            ->orderBy('b.created_at', 'DESC')
            ->get()->getResultArray();

        return [
            'summary' => [
                'pending_for_me' => $pendingForMe,
            ],
            'pending_bookings' => $pendingBookings,
        ];
    }
}

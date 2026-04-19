<?php
// app/Controllers/Api/BookingController.php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\BookingModel;
use App\Models\BookingApprovalModel;
use App\Models\VehicleModel;
use App\Models\DriverModel;
use App\Models\UserModel;
use App\Services\AuthContext;
use App\Services\ActivityLogService;
use App\Services\BookingService;
use CodeIgniter\Controller;
use Config\Database;
use RuntimeException;

class BookingController extends Controller
{
    private function getAuthUser(): array
    {
        return AuthContext::get();
    }

    // -------------------------------------------------------------------------
    // index() — list bookings
    // -------------------------------------------------------------------------
    public function index()
    {
        $authUser = $this->getAuthUser();
        $db = Database::connect();

        $builder = $db->table('bookings b')
            ->select('b.id, b.booking_code, b.requester_name, b.purpose, b.destination,
                    b.start_date, b.end_date, b.status, b.created_at,
                    v.name AS vehicle_name, v.plate_number,
                    d.name AS driver_name,
                    u.name AS admin_name')
            ->join('vehicles v', 'v.id = b.vehicle_id')
            ->join('drivers d', 'd.id = b.driver_id')
            ->join('users u', 'u.id = b.admin_id')
            ->orderBy('b.created_at', 'DESC');

        if ($authUser['role'] === 'approver') {
            $builder->join('booking_approvals ba', 'ba.booking_id = b.id')
                    ->where('ba.approver_id', $authUser['id']);
        }

        $status = $this->request->getGet('status');
        if ($status !== null && $status !== '') {
            $builder->where('b.status', $status);
        }

        $bookings = $builder->get()->getResultArray();

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Bookings retrieved',
                'data'    => $bookings,
            ]);
    }

    // -------------------------------------------------------------------------
    // show($id) — booking detail
    // -------------------------------------------------------------------------
    public function show($id)
    {
        $db = Database::connect();

        $booking = $db->table('bookings b')
            ->select('b.*')
            ->where('b.id', $id)
            ->get()
            ->getRowArray();

        if (!$booking) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Booking not found',
                    'data'    => null,
                ]);
        }

        // Vehicle
        $vehicle = $db->table('vehicles')
            ->select('id, name, plate_number, type, ownership')
            ->where('id', $booking['vehicle_id'])
            ->get()->getRowArray();

        // Driver
        $driver = $db->table('drivers')
            ->select('id, name, phone, license_number')
            ->where('id', $booking['driver_id'])
            ->get()->getRowArray();

        // Admin
        $admin = $db->table('users')
            ->select('id, name')
            ->where('id', $booking['admin_id'])
            ->get()->getRowArray();

        // Approvals with approver name
        $approvals = $db->table('booking_approvals ba')
            ->select('ba.id, ba.level, ba.status, ba.notes, ba.acted_at, u.name AS approver_name')
            ->join('users u', 'u.id = ba.approver_id')
            ->where('ba.booking_id', $id)
            ->orderBy('ba.level', 'ASC')
            ->get()->getResultArray();

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status'  => true,
                'message' => 'Booking retrieved',
                'data'    => array_merge($booking, [
                    'vehicle'   => $vehicle,
                    'driver'    => $driver,
                    'admin'     => $admin,
                    'approvals' => $approvals,
                ]),
            ]);
    }

    // -------------------------------------------------------------------------
    // create() — admin only
    // -------------------------------------------------------------------------
    public function create()
    {
        $authUser = $this->getAuthUser();

        if ($authUser['role'] !== 'admin') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['status' => false, 'message' => 'Forbidden', 'data' => null]);
        }

        $json = $this->request->getJSON(true) ?? [];

        // CI4 validation
        $validation = service('validation');
        $validation->setRules([
            'vehicle_id'        => 'required|is_natural_no_zero',
            'driver_id'         => 'required|is_natural_no_zero',
            'requester_name'    => 'required',
            'purpose'           => 'required',
            'destination'       => 'required',
            'start_date'        => 'required|valid_date[Y-m-d H:i:s]',
            'end_date'          => 'required|valid_date[Y-m-d H:i:s]',
            'approver_level1_id' => 'required|is_natural_no_zero',
            'approver_level2_id' => 'required|is_natural_no_zero',
        ]);

        if (!$validation->run($json)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => $validation->getErrors(),
                ]);
        }

        // Manual business-logic checks
        $errors = [];

        // Date order
        if (strtotime($json['start_date']) >= strtotime($json['end_date'])) {
            $errors['start_date'] = 'start_date must be before end_date.';
        }

        // Vehicle exists and is available
        $vehicleModel = new VehicleModel();
        $vehicle = $vehicleModel->find($json['vehicle_id']);
        if (!$vehicle) {
            $errors['vehicle_id'] = 'Vehicle not found.';
        } elseif ($vehicle['status'] !== 'available') {
            $errors['vehicle_id'] = 'Vehicle is not available for booking.';
        }

        // Driver exists and is active
        $driverModel = new DriverModel();
        $driver = $driverModel->find($json['driver_id']);
        if (!$driver) {
            $errors['driver_id'] = 'Driver not found.';
        } elseif ($driver['status'] !== 'active') {
            $errors['driver_id'] = 'Driver is not active.';
        }

        // Approver level 1
        $userModel = new UserModel();
        $approver1 = $userModel->find($json['approver_level1_id']);
        if (!$approver1 || $approver1['role'] !== 'approver' || (int)$approver1['approval_level'] !== 1) {
            $errors['approver_level1_id'] = 'Approver level 1 must be a user with role=approver and approval_level=1.';
        }

        // Approver level 2
        $approver2 = $userModel->find($json['approver_level2_id']);
        if (!$approver2 || $approver2['role'] !== 'approver' || (int)$approver2['approval_level'] !== 2) {
            $errors['approver_level2_id'] = 'Approver level 2 must be a user with role=approver and approval_level=2.';
        }

        // Self-approval prevention
        if ((int)$json['approver_level1_id'] === (int)$authUser['id']) {
            $errors['approver_level1_id'] = 'Admin cannot approve their own booking (level 1).';
        }
        if ((int)$json['approver_level2_id'] === (int)$authUser['id']) {
            $errors['approver_level2_id'] = 'Admin cannot approve their own booking (level 2).';
        }

        if (!empty($errors)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => $errors,
                ]);
        }

        // Delegate to BookingService
        try {
            $bookingService = new BookingService();
            $booking = $bookingService->createBooking(
                $json,
                (int)$authUser['id'],
                (int)$json['approver_level1_id'],
                (int)$json['approver_level2_id']
            );
        } catch (RuntimeException $e) {
            return $this->response
                ->setStatusCode(409)
                ->setJSON([
                    'status'  => false,
                    'message' => $e->getMessage(),
                    'data'    => null,
                ]);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'status'  => true,
                'message' => 'Booking created',
                'data'    => $booking,
            ]);
    }

    // -------------------------------------------------------------------------
    // approve($id) — approver only
    // -------------------------------------------------------------------------
    public function approve($id)
    {
        $authUser = $this->getAuthUser();

        if ($authUser['role'] !== 'approver') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['status' => false, 'message' => 'Forbidden', 'data' => null]);
        }

        $bookingModel = new BookingModel();
        $booking = $bookingModel->find($id);

        if (!$booking) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['status' => false, 'message' => 'Booking not found', 'data' => null]);
        }

        $approvalModel = new BookingApprovalModel();
        $approval = $approvalModel
            ->where('booking_id', $id)
            ->where('approver_id', $authUser['id'])
            ->first();

        if (!$approval) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['status' => false, 'message' => 'You are not assigned to this booking.', 'data' => null]);
        }

        // Check already acted
        if ($approval['status'] !== 'pending') {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => ['booking' => 'This booking has already been acted upon.'],
                ]);
        }

        // Check turn
        $level = (int)$approval['level'];
        $expectedStatus = $level === 1 ? 'waiting_level_1' : 'waiting_level_2';
        if ($booking['status'] !== $expectedStatus) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => ['booking' => 'It is not your turn to approve.'],
                ]);
        }

        $now = date('Y-m-d H:i:s');
        $newBookingStatus = $level === 1 ? 'waiting_level_2' : 'approved';

        $db = Database::connect();
        $db->transStart();

        $approvalModel->update($approval['id'], [
            'status'   => 'approved',
            'acted_at' => $now,
        ]);

        $bookingModel->update($id, ['status' => $newBookingStatus]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['status' => false, 'message' => 'Database error occurred.', 'data' => null]);
        }

        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'booking.approved',
            'booking',
            (int)$id,
            "Approver {$authUser['name']} (Level {$level}) approved booking {$booking['booking_code']}",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON(['status' => true, 'message' => 'Booking approved', 'data' => null]);
    }

    // -------------------------------------------------------------------------
    // reject($id) — approver only
    // -------------------------------------------------------------------------
    public function reject($id)
    {
        $authUser = $this->getAuthUser();

        if ($authUser['role'] !== 'approver') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['status' => false, 'message' => 'Forbidden', 'data' => null]);
        }

        $bookingModel = new BookingModel();
        $booking = $bookingModel->find($id);

        if (!$booking) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['status' => false, 'message' => 'Booking not found', 'data' => null]);
        }

        $approvalModel = new BookingApprovalModel();
        $approval = $approvalModel
            ->where('booking_id', $id)
            ->where('approver_id', $authUser['id'])
            ->first();

        if (!$approval) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['status' => false, 'message' => 'You are not assigned to this booking.', 'data' => null]);
        }

        // Check already acted
        if ($approval['status'] !== 'pending') {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => ['booking' => 'This booking has already been acted upon.'],
                ]);
        }

        // Check booking still actionable
        if (!in_array($booking['status'], ['waiting_level_1', 'waiting_level_2'], true)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => ['booking' => 'This booking has already been acted upon.'],
                ]);
        }

        // Require notes for rejection
        $json = $this->request->getJSON(true) ?? [];
        $notes = trim($json['notes'] ?? '');
        if ($notes === '') {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => ['notes' => 'A reason is required when rejecting a booking.'],
                ]);
        }

        $level = (int)$approval['level'];
        $now = date('Y-m-d H:i:s');

        $db = Database::connect();
        $db->transStart();

        $approvalModel->update($approval['id'], [
            'status'   => 'rejected',
            'notes'    => $notes,
            'acted_at' => $now,
        ]);

        $bookingModel->update($id, ['status' => 'rejected']);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['status' => false, 'message' => 'Database error occurred.', 'data' => null]);
        }

        $activityLogService = new ActivityLogService();
        $activityLogService->log(
            (int)$authUser['id'],
            'booking.rejected',
            'booking',
            (int)$id,
            "Approver {$authUser['name']} (Level {$level}) rejected booking {$booking['booking_code']}: {$notes}",
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON(['status' => true, 'message' => 'Booking rejected', 'data' => null]);
    }
}
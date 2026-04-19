<?php
// app/Services/BookingService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\BookingModel;
use App\Models\BookingApprovalModel;
use RuntimeException;
use Config\Database;

class BookingService
{
   public function generateBookingCode(): string
{
    $model = new BookingModel();

    $dateStr = date('Ymd');
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxAttempts = 10;

    for ($attempts = 0; $attempts < $maxAttempts; $attempts++) {
        $randomStr = '';
        for ($i = 0; $i < 4; $i++) {
            $randomStr .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $code = "BK-{$dateStr}-{$randomStr}";

        if ($model->where('booking_code', $code)->first() === null) {
            return $code;
        }
    }

    throw new RuntimeException('Failed to generate a unique booking code.');
}

    public function isVehicleAvailable(int $vehicleId, string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        $model = new BookingModel();
        
        $builder = $model->where('vehicle_id', $vehicleId)
                         ->whereIn('status', ['waiting_level_1', 'waiting_level_2', 'approved'])
                         ->where('start_date <', $endDate)
                         ->where('end_date >', $startDate);
                         
        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }
        
        return $builder->first() === null;
    }

    public function isDriverAvailable(int $driverId, string $startDate, string $endDate, ?int $excludeId = null): bool
    {
        $model = new BookingModel();
        
        $builder = $model->where('driver_id', $driverId)
                         ->whereIn('status', ['waiting_level_1', 'waiting_level_2', 'approved'])
                         ->where('start_date <', $endDate)
                         ->where('end_date >', $startDate);
                         
        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }
        
        return $builder->first() === null;
    }

    public function createBooking(array $data, int $adminId, int $approverLevel1Id, int $approverLevel2Id): array
    {
        $db = Database::connect();
        
        if (!$this->isVehicleAvailable((int)$data['vehicle_id'], $data['start_date'], $data['end_date'])) {
            throw new RuntimeException('Vehicle is not available for the selected dates.');
        }
        
        if (!$this->isDriverAvailable((int)$data['driver_id'], $data['start_date'], $data['end_date'])) {
            throw new RuntimeException('Driver is not available for the selected dates.');
        }
        
        $bookingCode = $this->generateBookingCode();
        
        $bookingData = [
            'booking_code'   => $bookingCode,
            'admin_id'       => $adminId,
            'vehicle_id'     => (int)$data['vehicle_id'],
            'driver_id'      => (int)$data['driver_id'],
            'requester_name' => $data['requester_name'],
            'purpose'        => $data['purpose'],
            'destination'    => $data['destination'],
            'start_date'     => $data['start_date'],
            'end_date'       => $data['end_date'],
            'status'         => 'waiting_level_1',
        ];
        
        $db->transStart();
        
        $bookingModel = new BookingModel();
        $bookingApprovalModel = new BookingApprovalModel();
        
        $bookingModel->insert($bookingData);
        $bookingId = $bookingModel->getInsertID();
        
        $bookingApprovalModel->insert([
            'booking_id'  => $bookingId,
            'approver_id' => $approverLevel1Id,
            'level'       => 1,
            'status'      => 'pending',
        ]);
        
        $bookingApprovalModel->insert([
            'booking_id'  => $bookingId,
            'approver_id' => $approverLevel2Id,
            'level'       => 2,
            'status'      => 'pending',
        ]);
        
        $db->transComplete();
        
        if ($db->transStatus() === false) {
            throw new RuntimeException('Failed to create booking due to database error.');
        }
        
        $activityLogService = new ActivityLogService();
        $request = service('request');
        $ipAddress = method_exists($request, 'getIPAddress') ? $request->getIPAddress() : '0.0.0.0';
        
        $activityLogService->log(
            $adminId,
            'booking.created',
            'booking',
            (int)$bookingId,
            "Admin created booking {$bookingCode}",
            $ipAddress
        );
        
        $bookingData['id'] = $bookingId;
        
        return $bookingData;
    }
}
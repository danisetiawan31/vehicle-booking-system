<?php

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\BookingService;
use App\Services\JwtService;
use Config\Database;

uses(FeatureTestTrait::class, DatabaseTestTrait::class);

beforeEach(function () {
    putenv('jwt.secret=test-secret-key-show-auth');

    $db = Database::connect();

    // Clean up tables
    $db->table('activity_logs')->emptyTable();
    $db->table('booking_approvals')->emptyTable();
    $db->table('bookings')->emptyTable();
    $db->table('users')->emptyTable();
    $db->table('vehicles')->emptyTable();
    $db->table('drivers')->emptyTable();

    $now = date('Y-m-d H:i:s');
    $db->table('users')->insertBatch([
        [
            'id'             => 1,
            'name'           => 'Admin Utama',
            'email'          => 'admin@test.com',
            'password'       => password_hash('password', PASSWORD_DEFAULT),
            'role'           => 'admin',
            'approval_level' => null,
            'created_at'     => $now,
        ],
        [
            'id'             => 2,
            'name'           => 'Approver Ter-assign L1',
            'email'          => 'approver1@test.com',
            'password'       => password_hash('password', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 1,
            'created_at'     => $now,
        ],
        [
            'id'             => 3,
            'name'           => 'Approver Ter-assign L2',
            'email'          => 'approver2@test.com',
            'password'       => password_hash('password', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 2,
            'created_at'     => $now,
        ],
        [
            'id'             => 4,
            'name'           => 'Approver Lain (Tidak Ter-assign)',
            'email'          => 'approver_other@test.com',
            'password'       => password_hash('password', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 1,
            'created_at'     => $now,
        ],
    ]);

    $db->table('vehicles')->insert([
        'id'           => 1,
        'name'         => 'Toyota Innova',
        'plate_number' => 'B 7777 ABC',
        'type'         => 'passenger',
        'ownership'    => 'own',
        'region'       => 'Jakarta',
        'status'       => 'available',
        'created_at'   => $now,
    ]);

    $db->table('drivers')->insert([
        'id'             => 1,
        'name'           => 'Slamet',
        'license_number' => 'SIM-A-888',
        'phone'          => '081299990000',
        'status'         => 'active',
        'created_at'     => $now,
    ]);

    $bookingService = new BookingService();
    $created = $bookingService->createBooking([
        'vehicle_id'     => 1,
        'driver_id'      => 1,
        'requester_name' => 'Karyawan Test',
        'purpose'        => 'Kunjungan Kerja',
        'destination'    => 'Surabaya',
        'start_date'     => date('Y-m-d H:i:s', strtotime('+1 day')),
        'end_date'       => date('Y-m-d H:i:s', strtotime('+2 days')),
    ], 1, 2, 3); // Admin ID 1, Approver L1 ID 2, Approver L2 ID 3

    $this->bookingId = (int)$created['id'];

    $jwtService = new JwtService();

    $this->adminToken = $jwtService->generate([
        'id'             => 1,
        'name'           => 'Admin Utama',
        'email'          => 'admin@test.com',
        'role'           => 'admin',
        'approval_level' => null,
    ]);

    $this->assignedApproverL1Token = $jwtService->generate([
        'id'             => 2,
        'name'           => 'Approver Ter-assign L1',
        'email'          => 'approver1@test.com',
        'role'           => 'approver',
        'approval_level' => 1,
    ]);

    $this->assignedApproverL2Token = $jwtService->generate([
        'id'             => 3,
        'name'           => 'Approver Ter-assign L2',
        'email'          => 'approver2@test.com',
        'role'           => 'approver',
        'approval_level' => 2,
    ]);

    $this->unassignedApproverToken = $jwtService->generate([
        'id'             => 4,
        'name'           => 'Approver Lain (Tidak Ter-assign)',
        'email'          => 'approver_other@test.com',
        'role'           => 'approver',
        'approval_level' => 1,
    ]);
});

test('assigned approver level 1 can access booking details', function () {
    $result = $this->withHeaders(['Authorization' => "Bearer {$this->assignedApproverL1Token}"])
                   ->get("api/bookings/{$this->bookingId}");

    $result->assertStatus(200);
    $json = json_decode($result->getJSON(), true);

    expect($json['status'])->toBeTrue();
    expect($json['message'])->toBe('Booking retrieved');
    expect($json['data']['id'])->toBe($this->bookingId);
    expect($json['data'])->toHaveKeys(['vehicle', 'driver', 'admin', 'approvals']);
});

test('assigned approver level 2 can access booking details', function () {
    $result = $this->withHeaders(['Authorization' => "Bearer {$this->assignedApproverL2Token}"])
                   ->get("api/bookings/{$this->bookingId}");

    $result->assertStatus(200);
    $json = json_decode($result->getJSON(), true);

    expect($json['status'])->toBeTrue();
    expect($json['message'])->toBe('Booking retrieved');
    expect($json['data']['id'])->toBe($this->bookingId);
    expect($json['data'])->toHaveKeys(['vehicle', 'driver', 'admin', 'approvals']);
});

test('unassigned approver receives 403 forbidden when trying to access booking details', function () {
    $result = $this->withHeaders(['Authorization' => "Bearer {$this->unassignedApproverToken}"])
                   ->get("api/bookings/{$this->bookingId}");

    $result->assertStatus(403);
    $json = json_decode($result->getJSON(), true);

    expect($json['status'])->toBeFalse();
    expect($json['message'])->toBe('Forbidden');
    expect($json['data'])->toBeNull();
});

test('admin can access any booking details without restriction', function () {
    $result = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
                   ->get("api/bookings/{$this->bookingId}");

    $result->assertStatus(200);
    $json = json_decode($result->getJSON(), true);

    expect($json['status'])->toBeTrue();
    expect($json['message'])->toBe('Booking retrieved');
    expect($json['data']['id'])->toBe($this->bookingId);
    expect($json['data'])->toHaveKeys(['vehicle', 'driver', 'admin', 'approvals']);
});

<?php

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\BookingService;
use App\Services\JwtService;
use Config\Database;

uses(FeatureTestTrait::class, DatabaseTestTrait::class);

beforeEach(function () {
    putenv('jwt.secret=test-secret-key-dashboard-approver');

    $db = Database::connect();

    // Clean up tables for test isolation
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
            'name'           => 'Approver Level 1',
            'email'          => 'approver1@test.com',
            'password'       => password_hash('password', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 1,
            'created_at'     => $now,
        ],
        [
            'id'             => 3,
            'name'           => 'Approver Level 2',
            'email'          => 'approver2@test.com',
            'password'       => password_hash('password', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 2,
            'created_at'     => $now,
        ],
    ]);

    $db->table('vehicles')->insert([
        'id'           => 1,
        'name'         => 'Honda CR-V',
        'plate_number' => 'B 1234 CRV',
        'type'         => 'passenger',
        'ownership'    => 'own',
        'region'       => 'Jakarta',
        'status'       => 'available',
        'created_at'   => $now,
    ]);

    $db->table('drivers')->insert([
        'id'             => 1,
        'name'           => 'Joko Susilo',
        'license_number' => 'SIM-A-555',
        'phone'          => '081233334444',
        'status'         => 'active',
        'created_at'     => $now,
    ]);

    $jwtService = new JwtService();

    $this->approver1Token = $jwtService->generate([
        'id'             => 2,
        'name'           => 'Approver Level 1',
        'email'          => 'approver1@test.com',
        'role'           => 'approver',
        'approval_level' => 1,
    ]);

    $this->approver2Token = $jwtService->generate([
        'id'             => 3,
        'name'           => 'Approver Level 2',
        'email'          => 'approver2@test.com',
        'role'           => 'approver',
        'approval_level' => 2,
    ]);
});

test('approver dashboard strictly shows pending bookings based on sequential turn level', function () {
    $bookingService = new BookingService();

    // 1. Create a new booking (initial status: waiting_level_1)
    $created = $bookingService->createBooking([
        'vehicle_id'     => 1,
        'driver_id'      => 1,
        'requester_name' => 'Karyawan Dashboard Test',
        'purpose'        => 'Dinas Luar Kota',
        'destination'    => 'Yogyakarta',
        'start_date'     => date('Y-m-d H:i:s', strtotime('+2 days')),
        'end_date'       => date('Y-m-d H:i:s', strtotime('+4 days')),
    ], 1, 2, 3); // Admin 1, Approver L1 (ID 2), Approver L2 (ID 3)

    $bookingId = (int)$created['id'];

    // 2. Check Approver L1 dashboard -> MUST show this booking (it's L1's turn)
    $resL1_step1 = $this->withHeaders(['Authorization' => "Bearer {$this->approver1Token}"])
                         ->get('api/dashboard');

    $resL1_step1->assertStatus(200);
    $jsonL1_step1 = json_decode($resL1_step1->getJSON(), true);

    expect($jsonL1_step1['data']['summary']['pending_for_me'])->toBe(1);
    expect($jsonL1_step1['data']['pending_bookings'])->toHaveCount(1);
    expect((int)$jsonL1_step1['data']['pending_bookings'][0]['booking_id'])->toBe($bookingId);
    expect((int)$jsonL1_step1['data']['pending_bookings'][0]['approval_level'])->toBe(1);

    // 3. Check Approver L2 dashboard -> MUST NOT show this booking (L1 hasn't approved yet!)
    $resL2_step1 = $this->withHeaders(['Authorization' => "Bearer {$this->approver2Token}"])
                         ->get('api/dashboard');

    $resL2_step1->assertStatus(200);
    $jsonL2_step1 = json_decode($resL2_step1->getJSON(), true);

    expect($jsonL2_step1['data']['summary']['pending_for_me'])->toBe(0);
    expect($jsonL2_step1['data']['pending_bookings'])->toHaveCount(0);

    // 4. Approver L1 approves the booking (status transitions to waiting_level_2)
    $approveRes = $this->withHeaders(['Authorization' => "Bearer {$this->approver1Token}"])
                       ->post("api/bookings/{$bookingId}/approve");
    $approveRes->assertStatus(200);

    // 5. Check Approver L1 dashboard -> MUST NO LONGER show this booking
    $resL1_step2 = $this->withHeaders(['Authorization' => "Bearer {$this->approver1Token}"])
                         ->get('api/dashboard');

    $resL1_step2->assertStatus(200);
    $jsonL1_step2 = json_decode($resL1_step2->getJSON(), true);

    expect($jsonL1_step2['data']['summary']['pending_for_me'])->toBe(0);
    expect($jsonL1_step2['data']['pending_bookings'])->toHaveCount(0);

    // 6. Check Approver L2 dashboard -> NOW MUST SHOW this booking (it's now L2's turn!)
    $resL2_step2 = $this->withHeaders(['Authorization' => "Bearer {$this->approver2Token}"])
                         ->get('api/dashboard');

    $resL2_step2->assertStatus(200);
    $jsonL2_step2 = json_decode($resL2_step2->getJSON(), true);

    expect($jsonL2_step2['data']['summary']['pending_for_me'])->toBe(1);
    expect($jsonL2_step2['data']['pending_bookings'])->toHaveCount(1);
    expect((int)$jsonL2_step2['data']['pending_bookings'][0]['booking_id'])->toBe($bookingId);
    expect((int)$jsonL2_step2['data']['pending_bookings'][0]['approval_level'])->toBe(2);
});

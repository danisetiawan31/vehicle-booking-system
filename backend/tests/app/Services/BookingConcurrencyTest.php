<?php

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\BookingService;
use App\Services\JwtService;
use Config\Database;

uses(FeatureTestTrait::class, DatabaseTestTrait::class);

beforeEach(function () {
    putenv('jwt.secret=test-secret-key-concurrency');

    $db = Database::connect();

    // Clean up tables for isolation
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
        'name'         => 'Toyota Fortuner',
        'plate_number' => 'B 9999 VIP',
        'type'         => 'passenger',
        'ownership'    => 'own',
        'region'       => 'Jakarta',
        'status'       => 'available',
        'created_at'   => $now,
    ]);

    $db->table('drivers')->insert([
        'id'             => 1,
        'name'           => 'Bambang Supriyadi',
        'license_number' => 'SIM-A-12345',
        'phone'          => '081122334455',
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
});

test('pessimistic lock blocks second connection while first connection holds transaction lock', function () {
    // Open 2 unshared database connections
    $connA = Database::connect('tests', false);
    $connB = Database::connect('tests', false);

    // Connection A begins transaction and locks vehicle row (id = 1)
    $connA->transBegin();
    $connA->query('SELECT id FROM vehicles WHERE id = 1 FOR UPDATE');

    // Connection B attempts to lock the exact same vehicle row using NOWAIT
    $locked = false;
    try {
        $connB->query('SELECT id FROM vehicles WHERE id = 1 FOR UPDATE NOWAIT');
    } catch (\Throwable $e) {
        $locked = true;
        expect($e->getMessage())->toContain('Lock')
            ->or($e->getMessage())->toContain('NOWAIT')
            ->or($e->getMessage())->toContain('DatabaseException');
    }
    expect($locked)->toBeTrue();

    // Connection A commits transaction (releasing the lock)
    $connA->transCommit();

    // Connection B attempts to lock the vehicle row again -> MUST NOW SUCCEED
    $resB = $connB->query('SELECT id FROM vehicles WHERE id = 1 FOR UPDATE')->getRowArray();
    expect((int)$resB['id'])->toBe(1);

    $connB->close();
    $connA->close();
});

test('two sequential createBooking calls for overlapping dates result in exactly 1 booking', function () {
    $bookingService = new BookingService();

    $bookingData = [
        'vehicle_id'     => 1,
        'driver_id'      => 1,
        'requester_name' => 'Karyawan A',
        'purpose'        => 'Dinas Lapangan',
        'destination'    => 'Bandung',
        'start_date'     => date('Y-m-d H:i:s', strtotime('+1 day')),
        'end_date'       => date('Y-m-d H:i:s', strtotime('+3 days')),
    ];

    // First call: Should succeed
    $result1 = $bookingService->createBooking($bookingData, 1, 2, 3);
    expect($result1)->toHaveKey('id');
    expect($result1['id'])->toBeGreaterThan(0);

    // Second call with overlapping dates: Should throw RuntimeException
    $overlappingData = array_merge($bookingData, [
        'requester_name' => 'Karyawan B',
        'start_date'     => date('Y-m-d H:i:s', strtotime('+2 days')),
        'end_date'       => date('Y-m-d H:i:s', strtotime('+4 days')),
    ]);

    expect(fn () => $bookingService->createBooking($overlappingData, 1, 2, 3))
        ->toThrow(RuntimeException::class, 'Vehicle is not available for the selected dates.');

    // Assert that ONLY 1 booking exists in the database
    $db = Database::connect();
    $count = $db->table('bookings')->countAllResults();
    expect($count)->toBe(1);
});

test('two sequential approve calls for the same booking result in 1 success and 1 422 error without double logs', function () {
    $bookingService = new BookingService();

    $bookingData = [
        'vehicle_id'     => 1,
        'driver_id'      => 1,
        'requester_name' => 'Karyawan C',
        'purpose'        => 'Rapat Cabang',
        'destination'    => 'Semarang',
        'start_date'     => date('Y-m-d H:i:s', strtotime('+5 days')),
        'end_date'       => date('Y-m-d H:i:s', strtotime('+7 days')),
    ];

    $created = $bookingService->createBooking($bookingData, 1, 2, 3);
    $bookingId = (int)$created['id'];

    // First approve call: Should succeed (200)
    $response1 = $this->withHeaders(['Authorization' => "Bearer {$this->approver1Token}"])
                      ->post("api/bookings/{$bookingId}/approve");

    $response1->assertStatus(200);
    $json1 = json_decode($response1->getJSON(), true);
    expect($json1['status'])->toBeTrue();

    // Second approve call: Should fail (422) with "already acted upon"
    $response2 = $this->withHeaders(['Authorization' => "Bearer {$this->approver1Token}"])
                      ->post("api/bookings/{$bookingId}/approve");

    $response2->assertStatus(422);
    $json2 = json_decode($response2->getJSON(), true);
    expect($json2['status'])->toBeFalse();
    expect($json2['errors']['booking'])->toBe('This booking has already been acted upon.');

    // Assert DB state: Booking status updated to waiting_level_2, approval level 1 is approved
    $db = Database::connect();
    $bookingInDb = $db->table('bookings')->where('id', $bookingId)->get()->getRowArray();
    expect($bookingInDb['status'])->toBe('waiting_level_2');

    $approvalL1 = $db->table('booking_approvals')
                     ->where('booking_id', $bookingId)
                     ->where('level', 1)
                     ->get()->getRowArray();
    expect($approvalL1['status'])->toBe('approved');

    // Assert Activity Logs: Exactly 1 log entry for booking.approved
    $logCount = $db->table('activity_logs')
                   ->where('action', 'booking.approved')
                   ->where('entity_id', $bookingId)
                   ->countAllResults();

    expect($logCount)->toBe(1);
});

test('two sequential reject calls for the same booking result in 1 success and 1 422 error without double logs', function () {
    $bookingService = new BookingService();

    $bookingData = [
        'vehicle_id'     => 1,
        'driver_id'      => 1,
        'requester_name' => 'Karyawan D',
        'purpose'        => 'Survei Tambang',
        'destination'    => 'Palembang',
        'start_date'     => date('Y-m-d H:i:s', strtotime('+10 days')),
        'end_date'       => date('Y-m-d H:i:s', strtotime('+12 days')),
    ];

    $created = $bookingService->createBooking($bookingData, 1, 2, 3);
    $bookingId = (int)$created['id'];

    // First reject call: Should succeed (200)
    $response1 = $this->withHeaders([
        'Authorization' => "Bearer {$this->approver1Token}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode(['notes' => 'Mobil sedang dalam perbaikan']))
      ->post("api/bookings/{$bookingId}/reject");

    $response1->assertStatus(200);
    $json1 = json_decode($response1->getJSON(), true);
    expect($json1['status'])->toBeTrue();

    // Second reject call: Should fail (422) with "already acted upon"
    $response2 = $this->withHeaders([
        'Authorization' => "Bearer {$this->approver1Token}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode(['notes' => 'Penolakan kedua kali']))
      ->post("api/bookings/{$bookingId}/reject");

    $response2->assertStatus(422);
    $json2 = json_decode($response2->getJSON(), true);
    expect($json2['status'])->toBeFalse();
    expect($json2['errors']['booking'])->toBe('This booking has already been acted upon.');

    // Assert DB state: Booking status updated to rejected, approval level 1 is rejected with notes
    $db = Database::connect();
    $bookingInDb = $db->table('bookings')->where('id', $bookingId)->get()->getRowArray();
    expect($bookingInDb['status'])->toBe('rejected');

    $approvalL1 = $db->table('booking_approvals')
                     ->where('booking_id', $bookingId)
                     ->where('level', 1)
                     ->get()->getRowArray();
    expect($approvalL1['status'])->toBe('rejected');
    expect($approvalL1['notes'])->toBe('Mobil sedang dalam perbaikan');

    // Assert Activity Logs: Exactly 1 log entry for booking.rejected
    $logCount = $db->table('activity_logs')
                   ->where('action', 'booking.rejected')
                   ->where('entity_id', $bookingId)
                   ->countAllResults();

    expect($logCount)->toBe(1);
});

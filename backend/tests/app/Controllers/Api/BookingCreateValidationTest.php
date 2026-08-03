<?php

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\JwtService;
use Config\Database;

uses(FeatureTestTrait::class, DatabaseTestTrait::class);

beforeEach(function () {
    putenv('jwt.secret=test-secret-key-123456');

    $db = Database::connect();

    // Clean up tables for isolation
    $db->table('booking_approvals')->emptyTable();
    $db->table('bookings')->emptyTable();
    $db->table('users')->emptyTable();
    $db->table('vehicles')->emptyTable();
    $db->table('drivers')->emptyTable();

    // Seed test users
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
            'name'           => 'Approver L1',
            'email'          => 'approver1@test.com',
            'password'       => password_hash('password', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 1,
            'created_at'     => $now,
        ],
        [
            'id'             => 3,
            'name'           => 'Approver L2',
            'email'          => 'approver2@test.com',
            'password'       => password_hash('password', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 2,
            'created_at'     => $now,
        ],
        [
            'id'             => 4,
            'name'           => 'Admin Approver L1 Combo',
            'email'          => 'admin_approver1@test.com',
            'password'       => password_hash('password', PASSWORD_DEFAULT),
            'role'           => 'admin',
            'approval_level' => null,
            'created_at'     => $now,
        ],
    ]);

    // Seed test vehicles
    $db->table('vehicles')->insertBatch([
        [
            'id'           => 1,
            'name'         => 'Toyota Avanza',
            'plate_number' => 'B 1234 ABC',
            'type'         => 'passenger',
            'ownership'    => 'own',
            'region'       => 'Jakarta',
            'status'       => 'available',
            'created_at'   => $now,
        ],
        [
            'id'           => 2,
            'name'         => 'Isuzu Elf',
            'plate_number' => 'B 5678 DEF',
            'type'         => 'cargo',
            'ownership'    => 'rental',
            'region'       => 'Jakarta',
            'status'       => 'maintenance',
            'created_at'   => $now,
        ],
    ]);

    // Seed test drivers
    $db->table('drivers')->insertBatch([
        [
            'id'             => 1,
            'name'           => 'Driver Aktif',
            'license_number' => 'SIM-001',
            'phone'          => '08123456789',
            'status'         => 'active',
            'created_at'     => $now,
        ],
        [
            'id'             => 2,
            'name'           => 'Driver Inaktif',
            'license_number' => 'SIM-002',
            'phone'          => '08987654321',
            'status'         => 'inactive',
            'created_at'     => $now,
        ],
    ]);

    $jwtService = new JwtService();
    $this->adminToken = $jwtService->generate([
        'id'             => 1,
        'name'           => 'Admin Utama',
        'email'          => 'admin@test.com',
        'role'           => 'admin',
        'approval_level' => null,
    ]);

    $this->validPayload = [
        'vehicle_id'         => 1,
        'driver_id'          => 1,
        'requester_name'     => 'Budi Santoso',
        'purpose'            => 'Kunjungan Lapangan',
        'destination'        => 'Site Tambang',
        'start_date'         => date('Y-m-d H:i:s', strtotime('+1 day')),
        'end_date'           => date('Y-m-d H:i:s', strtotime('+2 days')),
        'approver_level1_id' => 2,
        'approver_level2_id' => 3,
    ];
});

function postJsonBooking($testInstance, string $token, array $payload)
{
    return $testInstance->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->post('api/bookings');
}

test('returns 422 when start_date is greater than or equal to end_date', function () {
    $payload = array_merge($this->validPayload, [
        'start_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
        'end_date'   => date('Y-m-d H:i:s', strtotime('+1 day')),
    ]);

    $result = postJsonBooking($this, $this->adminToken, $payload);

    $result->assertStatus(422);
    $json = json_decode($result->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('start_date');
});

test('returns 422 when vehicle is not available', function () {
    $payload = array_merge($this->validPayload, [
        'vehicle_id' => 2, // status: maintenance
    ]);

    $result = postJsonBooking($this, $this->adminToken, $payload);

    $result->assertStatus(422);
    $json = json_decode($result->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('vehicle_id');
});

test('returns 422 when driver is not active', function () {
    $payload = array_merge($this->validPayload, [
        'driver_id' => 2, // status: inactive
    ]);

    $result = postJsonBooking($this, $this->adminToken, $payload);

    $result->assertStatus(422);
    $json = json_decode($result->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('driver_id');
});

test('returns 422 when approver_level1_id is invalid or not level 1 approver', function () {
    $payload = array_merge($this->validPayload, [
        'approver_level1_id' => 3, // approval_level is 2
    ]);

    $result = postJsonBooking($this, $this->adminToken, $payload);

    $result->assertStatus(422);
    $json = json_decode($result->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('approver_level1_id');
});

test('returns 422 when approver_level2_id is invalid or not level 2 approver', function () {
    $payload = array_merge($this->validPayload, [
        'approver_level2_id' => 2, // approval_level is 1
    ]);

    $result = postJsonBooking($this, $this->adminToken, $payload);

    $result->assertStatus(422);
    $json = json_decode($result->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('approver_level2_id');
});

test('returns 422 when admin attempts self-approval', function () {
    $jwtService = new JwtService();
    $adminApproverToken = $jwtService->generate([
        'id'             => 4,
        'name'           => 'Admin Approver L1 Combo',
        'email'          => 'admin_approver1@test.com',
        'role'           => 'admin',
        'approval_level' => null,
    ]);

    $payload = array_merge($this->validPayload, [
        'approver_level1_id' => 4, // Self-approval attempt (admin_id 4 === approver_level1_id 4)
    ]);

    $result = postJsonBooking($this, $adminApproverToken, $payload);

    $result->assertStatus(422);
    $json = json_decode($result->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('approver_level1_id');
});

test('returns 422 when approver_level1_id and approver_level2_id are the same user', function () {
    $payload = array_merge($this->validPayload, [
        'approver_level1_id' => 2,
        'approver_level2_id' => 2,
    ]);

    $result = postJsonBooking($this, $this->adminToken, $payload);

    $result->assertStatus(422);
    $json = json_decode($result->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('approver_level2_id');
});

test('returns 201 and creates booking with 2 approval records when payload is valid', function () {
    $result = postJsonBooking($this, $this->adminToken, $this->validPayload);

    $result->assertStatus(201);
    $json = json_decode($result->getJSON(), true);

    expect($json['status'])->toBeTrue();
    expect($json['message'])->toBe('Booking created');
    expect($json['data'])->toHaveKey('booking_code');

    $dateStr = date('Ymd');
    expect($json['data']['booking_code'])->toMatch("/^BK-{$dateStr}-[A-Z0-9]{4}$/");

    $db = Database::connect();
    $bookingId = (int)$json['data']['id'];

    $bookingInDb = $db->table('bookings')->where('id', $bookingId)->get()->getRowArray();
    expect($bookingInDb)->not->toBeNull();
    expect($bookingInDb['status'])->toBe('waiting_level_1');

    $approvalsInDb = $db->table('booking_approvals')
                        ->where('booking_id', $bookingId)
                        ->orderBy('level', 'ASC')
                        ->get()->getResultArray();

    expect(count($approvalsInDb))->toBe(2);
    expect((int)$approvalsInDb[0]['level'])->toBe(1);
    expect((int)$approvalsInDb[0]['approver_id'])->toBe(2);
    expect($approvalsInDb[0]['status'])->toBe('pending');

    expect((int)$approvalsInDb[1]['level'])->toBe(2);
    expect((int)$approvalsInDb[1]['approver_id'])->toBe(3);
    expect($approvalsInDb[1]['status'])->toBe('pending');
});
<?php

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\JwtService;
use Config\Database;

uses(FeatureTestTrait::class, DatabaseTestTrait::class);

beforeEach(function () {
    putenv('jwt.secret=test-secret-key-activity-log-service');

    $db = Database::connect();

    // Clean up tables
    $db->table('activity_logs')->emptyTable();
    $db->table('booking_approvals')->emptyTable();
    $db->table('bookings')->emptyTable();
    $db->table('vehicles')->emptyTable();
    $db->table('drivers')->emptyTable();
    $db->table('users')->emptyTable();

    $now = date('Y-m-d H:i:s');

    $db->table('users')->insertBatch([
        [
            'id'             => 1,
            'name'           => 'Admin Utama',
            'email'          => 'admin@test.com',
            'password'       => password_hash('password123', PASSWORD_DEFAULT),
            'role'           => 'admin',
            'approval_level' => null,
            'created_at'     => $now,
        ],
        [
            'id'             => 2,
            'name'           => 'Approver L1',
            'email'          => 'approver1@test.com',
            'password'       => password_hash('password123', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 1,
            'created_at'     => $now,
        ],
        [
            'id'             => 3,
            'name'           => 'Approver L2',
            'email'          => 'approver2@test.com',
            'password'       => password_hash('password123', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 2,
            'created_at'     => $now,
        ],
    ]);

    $db->table('vehicles')->insert([
        'id'           => 1,
        'name'         => 'Toyota Avanza',
        'plate_number' => 'B 1234 ABC',
        'type'         => 'passenger',
        'ownership'    => 'own',
        'region'       => 'Jakarta',
        'status'       => 'available',
        'created_at'   => $now,
    ]);

    $db->table('drivers')->insert([
        'id'             => 1,
        'name'           => 'Budi Santoso',
        'license_number' => 'SIM-001',
        'phone'          => '08123456789',
        'status'         => 'active',
        'created_at'     => $now,
    ]);

    $jwtService = new JwtService();
    $this->adminToken = $jwtService->generate([
        'id'             => 1,
        'name'           => 'Admin Utama',
        'email'          => 'admin@test.com',
        'role'           => 'admin',
        'approval_level' => null,
    ]);

    $this->approver1Token = $jwtService->generate([
        'id'             => 2,
        'name'           => 'Approver L1',
        'email'          => 'approver1@test.com',
        'role'           => 'approver',
        'approval_level' => 1,
    ]);

    $this->approver2Token = $jwtService->generate([
        'id'             => 3,
        'name'           => 'Approver L2',
        'email'          => 'approver2@test.com',
        'role'           => 'approver',
        'approval_level' => 2,
    ]);
});

// Helper assertion
function assertActivityLogged(string $action, string $entityType, int $userId): void {
    $db = Database::connect();
    $log = $db->table('activity_logs')
        ->where('action', $action)
        ->where('entity_type', $entityType)
        ->where('user_id', $userId)
        ->get()->getRowArray();

    expect($log)->not()->toBeNull();
}

// -----------------------------------------------------------------------------
// 1. Auth Login Activity Log
// -----------------------------------------------------------------------------
it('logs user.login on successful login', function () {
    $response = $this->withBodyFormat('json')
        ->post('/api/auth/login', [
            'email'    => 'admin@test.com',
            'password' => 'password123',
        ]);

    $response->assertStatus(200);
    assertActivityLogged('user.login', 'user', 1);
});

// -----------------------------------------------------------------------------
// 2. User CRUD Activity Logs
// -----------------------------------------------------------------------------
it('logs user.created on POST /api/users', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/users', [
            'name'           => 'User Baru',
            'email'          => 'userbaru@test.com',
            'password'       => 'password123',
            'role'           => 'approver',
            'approval_level' => 1,
        ]);

    $response->assertStatus(201);
    assertActivityLogged('user.created', 'user', 1);
});

it('logs user.updated on PUT /api/users/(:num)', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->put('/api/users/2', [
            'name' => 'Approver Level 1 Updated',
        ]);

    $response->assertStatus(200);
    assertActivityLogged('user.updated', 'user', 1);
});

it('logs user.deleted on DELETE /api/users/(:num)', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->delete('/api/users/3');

    $response->assertStatus(200);
    assertActivityLogged('user.deleted', 'user', 1);
});

// -----------------------------------------------------------------------------
// 3. Vehicle CRUD Activity Logs
// -----------------------------------------------------------------------------
it('logs vehicle.created on POST /api/vehicles', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/vehicles', [
            'name'         => 'Honda Jazz',
            'plate_number' => 'B 9999 JZZ',
            'type'         => 'passenger',
            'ownership'    => 'own',
            'region'       => 'Jakarta',
            'status'       => 'available',
        ]);

    $response->assertStatus(201);
    assertActivityLogged('vehicle.created', 'vehicle', 1);
});

it('logs vehicle.updated on PUT /api/vehicles/(:num)', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->put('/api/vehicles/1', [
            'region' => 'Bandung',
        ]);

    $response->assertStatus(200);
    assertActivityLogged('vehicle.updated', 'vehicle', 1);
});

it('logs vehicle.deleted on DELETE /api/vehicles/(:num)', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->delete('/api/vehicles/1');

    $response->assertStatus(200);
    assertActivityLogged('vehicle.deleted', 'vehicle', 1);
});

// -----------------------------------------------------------------------------
// 4. Driver CRUD Activity Logs
// -----------------------------------------------------------------------------
it('logs driver.created on POST /api/drivers', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/drivers', [
            'name'           => 'Driver Baru',
            'license_number' => 'SIM-999',
            'phone'          => '089999999',
            'status'         => 'active',
        ]);

    $response->assertStatus(201);
    assertActivityLogged('driver.created', 'driver', 1);
});

it('logs driver.updated on PUT /api/drivers/(:num)', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->put('/api/drivers/1', [
            'phone' => '080000000',
        ]);

    $response->assertStatus(200);
    assertActivityLogged('driver.updated', 'driver', 1);
});

it('logs driver.deleted on DELETE /api/drivers/(:num)', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->delete('/api/drivers/1');

    $response->assertStatus(200);
    assertActivityLogged('driver.deleted', 'driver', 1);
});

// -----------------------------------------------------------------------------
// 5. Booking Create, Approve, Reject Activity Logs
// -----------------------------------------------------------------------------
it('logs booking.created on POST /api/bookings', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/bookings', [
            'vehicle_id'          => 1,
            'driver_id'           => 1,
            'approver_level1_id' => 2,
            'approver_level2_id' => 3,
            'requester_name'      => 'Pemohon Audit',
            'purpose'             => 'Kunjungan Kerja',
            'destination'         => 'Kantor Cabang',
            'start_date'          => '2026-09-01 08:00:00',
            'end_date'            => '2026-09-02 17:00:00',
        ]);

    $response->assertStatus(201);
    assertActivityLogged('booking.created', 'booking', 1);
});

it('logs booking.approved on POST /api/bookings/(:num)/approve', function () {
    $db = Database::connect();
    $now = date('Y-m-d H:i:s');

    $db->table('bookings')->insert([
        'id'             => 10,
        'booking_code'   => 'BK-20260901-APPR',
        'admin_id'       => 1,
        'vehicle_id'     => 1,
        'driver_id'      => 1,
        'requester_name' => 'Pemohon',
        'purpose'        => 'Dinas',
        'destination'    => 'Jakarta',
        'start_date'     => '2026-09-01 08:00:00',
        'end_date'       => '2026-09-02 17:00:00',
        'status'         => 'waiting_level_1',
        'created_at'     => $now,
    ]);

    $db->table('booking_approvals')->insert([
        'booking_id'  => 10,
        'approver_id' => 2,
        'level'       => 1,
        'status'      => 'pending',
    ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->approver1Token])
        ->post('/api/bookings/10/approve');

    $response->assertStatus(200);
    assertActivityLogged('booking.approved', 'booking', 2);
});

it('logs booking.rejected on POST /api/bookings/(:num)/reject', function () {
    $db = Database::connect();
    $now = date('Y-m-d H:i:s');

    $db->table('bookings')->insert([
        'id'             => 20,
        'booking_code'   => 'BK-20260901-REJE',
        'admin_id'       => 1,
        'vehicle_id'     => 1,
        'driver_id'      => 1,
        'requester_name' => 'Pemohon',
        'purpose'        => 'Dinas',
        'destination'    => 'Jakarta',
        'start_date'     => '2026-09-01 08:00:00',
        'end_date'       => '2026-09-02 17:00:00',
        'status'         => 'waiting_level_1',
        'created_at'     => $now,
    ]);

    $db->table('booking_approvals')->insert([
        'booking_id'  => 20,
        'approver_id' => 2,
        'level'       => 1,
        'status'      => 'pending',
    ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->approver1Token])
        ->withBodyFormat('json')
        ->post('/api/bookings/20/reject', [
            'notes' => 'Alasan penolakan di-log',
        ]);

    $response->assertStatus(200);
    assertActivityLogged('booking.rejected', 'booking', 2);
});

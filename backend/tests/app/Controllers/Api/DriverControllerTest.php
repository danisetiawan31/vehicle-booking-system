<?php

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\JwtService;
use Config\Database;

uses(FeatureTestTrait::class, DatabaseTestTrait::class);

beforeEach(function () {
    putenv('jwt.secret=test-secret-key-driver-controller');

    $db = Database::connect();

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

    $db->table('drivers')->insertBatch([
        [
            'id'             => 1,
            'name'           => 'Budi Santoso',
            'license_number' => 'SIM-001',
            'phone'          => '08111111111',
            'status'         => 'active',
            'created_at'     => $now,
        ],
        [
            'id'             => 2,
            'name'           => 'Sari Dewi',
            'license_number' => 'SIM-002',
            'phone'          => '08222222222',
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
});

// =============================================================================
// GET /api/drivers?status=
// =============================================================================

it('GET /api/drivers returns only active drivers when status=active', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/drivers?status=active');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(true);
    expect($body['data'])->toHaveCount(1);
    expect($body['data'][0]['name'])->toBe('Budi Santoso');
    expect($body['data'][0]['status'])->toBe('active');
});

it('GET /api/drivers returns only inactive drivers when status=inactive', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/drivers?status=inactive');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['data'])->toHaveCount(1);
    expect($body['data'][0]['name'])->toBe('Sari Dewi');
});

it('GET /api/drivers without status filter returns all drivers', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/drivers');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect(count($body['data']))->toBeGreaterThanOrEqual(2);
});

// =============================================================================
// POST /api/drivers
// =============================================================================

it('POST /api/drivers returns 201 with valid payload', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/drivers', [
            'name'           => 'Ahmad Fauzi',
            'license_number' => 'SIM-999',
            'phone'          => '08999999999',
            'status'         => 'active',
        ]);

    $response->assertStatus(201);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(true);
    expect($body['data']['name'])->toBe('Ahmad Fauzi');
});

it('POST /api/drivers returns 422 when required fields are missing', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/drivers', ['name' => 'Incomplete Driver']);

    $response->assertStatus(422);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(false);
    // Technical debt: DriverController returns errors under key 'data', not 'errors'
    expect($body)->toHaveKey('data');
    expect($body['data'])->toHaveKey('license_number');
    expect($body['data'])->toHaveKey('phone');
});

it('POST /api/drivers returns 422 when status is outside valid enum', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/drivers', [
            'name'           => 'Wrong Status Driver',
            'license_number' => 'SIM-777',
            'phone'          => '08777777777',
            'status'         => 'suspended',
        ]);

    $response->assertStatus(422);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(false);
    expect($body['data'])->toHaveKey('status');
});

// =============================================================================
// PUT /api/drivers/(:num)
// =============================================================================

it('PUT /api/drivers/(:num) partial update succeeds and other fields remain unchanged', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->put('/api/drivers/1', ['phone' => '08100000000']);

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['data']['phone'])->toBe('08100000000');
    expect($body['data']['name'])->toBe('Budi Santoso');
    expect($body['data']['license_number'])->toBe('SIM-001');
});

it('PUT /api/drivers/(:num) returns 404 when id does not exist', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->put('/api/drivers/99999', ['phone' => '081000']);

    $response->assertStatus(404);
});

it('PUT /api/drivers/(:num) returns 422 when status is outside valid enum', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->put('/api/drivers/1', ['status' => 'on_leave']);

    $response->assertStatus(422);
    $body = json_decode($response->getJSON(), true);
    expect($body['data'])->toHaveKey('status');
});

// =============================================================================
// DELETE /api/drivers/(:num)
// =============================================================================

it('DELETE /api/drivers/(:num) succeeds and driver is removed from DB when no bookings', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->delete('/api/drivers/2');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(true);

    $db = Database::connect();
    $count = $db->table('drivers')->where('id', 2)->countAllResults();
    expect($count)->toBe(0);
});

it('DELETE /api/drivers/(:num) returns 404 when id does not exist', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->delete('/api/drivers/99999');

    $response->assertStatus(404);
});

it('DELETE /api/drivers/(:num) is rejected gracefully when driver is referenced by a booking', function () {
    $db = Database::connect();
    $now = date('Y-m-d H:i:s');

    $db->table('bookings')->insert([
        'id'             => 1,
        'booking_code'   => 'BK-20260101-DRIV',
        'admin_id'       => 1,
        'vehicle_id'     => 1,
        'driver_id'      => 1,
        'requester_name' => 'Test Requester',
        'purpose'        => 'Test purpose',
        'destination'    => 'Test destination',
        'start_date'     => '2026-09-01 08:00:00',
        'end_date'       => '2026-09-02 17:00:00',
        'status'         => 'waiting_level_1',
        'created_at'     => $now,
    ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->delete('/api/drivers/1');

    // Must NOT crash with 500. Must be graceful 4xx.
    $statusCode = $response->getStatusCode();
    expect($statusCode)->not->toBe(500);
    expect($statusCode)->toBeGreaterThanOrEqual(400);
    expect($statusCode)->toBeLessThan(500);

    // Driver must still exist in DB
    $count = $db->table('drivers')->where('id', 1)->countAllResults();
    expect($count)->toBe(1);
});

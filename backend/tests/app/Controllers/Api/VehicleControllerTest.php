<?php

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\JwtService;
use Config\Database;

uses(FeatureTestTrait::class, DatabaseTestTrait::class);

beforeEach(function () {
    putenv('jwt.secret=test-secret-key-vehicle-controller');

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
            'name'         => 'Mitsubishi L300',
            'plate_number' => 'B 5678 XYZ',
            'type'         => 'cargo',
            'ownership'    => 'rental',
            'region'       => 'Bandung',
            'status'       => 'maintenance',
            'created_at'   => $now,
        ],
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
});

// =============================================================================
// GET /api/vehicles?status=
// =============================================================================

it('GET /api/vehicles returns only available vehicles when status=available', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/vehicles?status=available');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(true);
    expect($body['data'])->toHaveCount(1);
    expect($body['data'][0]['plate_number'])->toBe('B 1234 ABC');
});

it('GET /api/vehicles returns only maintenance vehicles when status=maintenance', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/vehicles?status=maintenance');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['data'])->toHaveCount(1);
    expect($body['data'][0]['plate_number'])->toBe('B 5678 XYZ');
});

it('GET /api/vehicles without status filter returns all vehicles', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/vehicles');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect(count($body['data']))->toBeGreaterThanOrEqual(2);
});

// =============================================================================
// POST /api/vehicles
// =============================================================================

it('POST /api/vehicles returns 201 with valid payload', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/vehicles', [
            'name'         => 'Honda CR-V',
            'plate_number' => 'D 9999 ZZZ',
            'type'         => 'passenger',
            'ownership'    => 'own',
            'region'       => 'Surabaya',
            'status'       => 'available',
        ]);

    $response->assertStatus(201);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(true);
    expect($body['data']['plate_number'])->toBe('D 9999 ZZZ');
});

it('POST /api/vehicles returns 422 when required fields are missing', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/vehicles', ['name' => 'Incomplete']);

    $response->assertStatus(422);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(false);
    // Technical debt: VehicleController returns errors under key 'data', not 'errors'
    expect($body)->toHaveKey('data');
    expect($body['data'])->toHaveKey('plate_number');
});

it('POST /api/vehicles returns 422 when plate_number is duplicate', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/vehicles', [
            'name'         => 'Duplikat',
            'plate_number' => 'B 1234 ABC',
            'type'         => 'passenger',
            'ownership'    => 'own',
            'region'       => 'Jakarta',
        ]);

    $response->assertStatus(422);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(false);
    expect($body['data'])->toHaveKey('plate_number');
});

it('POST /api/vehicles returns 422 when type is outside valid enum', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/vehicles', [
            'name'         => 'Wrong Type',
            'plate_number' => 'E 0001 AAA',
            'type'         => 'suv',
            'ownership'    => 'own',
            'region'       => 'Jakarta',
        ]);

    $response->assertStatus(422);
    $body = json_decode($response->getJSON(), true);
    expect($body['data'])->toHaveKey('type');
});

it('POST /api/vehicles returns 422 when ownership is outside valid enum', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/vehicles', [
            'name'         => 'Wrong Ownership',
            'plate_number' => 'E 0002 BBB',
            'type'         => 'passenger',
            'ownership'    => 'leasing',
            'region'       => 'Jakarta',
        ]);

    $response->assertStatus(422);
    $body = json_decode($response->getJSON(), true);
    expect($body['data'])->toHaveKey('ownership');
});

it('POST /api/vehicles returns 422 when status is outside valid enum', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->post('/api/vehicles', [
            'name'         => 'Wrong Status',
            'plate_number' => 'E 0003 CCC',
            'type'         => 'cargo',
            'ownership'    => 'rental',
            'region'       => 'Jakarta',
            'status'       => 'broken',
        ]);

    $response->assertStatus(422);
    $body = json_decode($response->getJSON(), true);
    expect($body['data'])->toHaveKey('status');
});

// =============================================================================
// PUT /api/vehicles/(:num)
// =============================================================================

it('PUT /api/vehicles/(:num) partial update succeeds and other fields remain unchanged', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->put('/api/vehicles/1', ['region' => 'Surabaya Updated']);

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['data']['region'])->toBe('Surabaya Updated');
    expect($body['data']['plate_number'])->toBe('B 1234 ABC');
    expect($body['data']['type'])->toBe('passenger');
});

it('PUT /api/vehicles/(:num) returns 404 when id does not exist', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->put('/api/vehicles/99999', ['region' => 'Somewhere']);

    $response->assertStatus(404);
});

it('PUT /api/vehicles/(:num) returns 422 when plate_number conflicts with another vehicle', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->withBodyFormat('json')
        ->put('/api/vehicles/1', ['plate_number' => 'B 5678 XYZ']);

    $response->assertStatus(422);
    $body = json_decode($response->getJSON(), true);
    expect($body['data'])->toHaveKey('plate_number');
});

// =============================================================================
// DELETE /api/vehicles/(:num)
// =============================================================================

it('DELETE /api/vehicles/(:num) succeeds and vehicle is removed from DB when no bookings', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->delete('/api/vehicles/2');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(true);

    $db = Database::connect();
    $count = $db->table('vehicles')->where('id', 2)->countAllResults();
    expect($count)->toBe(0);
});

it('DELETE /api/vehicles/(:num) returns 404 when id does not exist', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->delete('/api/vehicles/99999');

    $response->assertStatus(404);
});

it('DELETE /api/vehicles/(:num) is rejected gracefully when vehicle is referenced by a booking', function () {
    $db = Database::connect();
    $now = date('Y-m-d H:i:s');

    $db->table('bookings')->insert([
        'id'             => 1,
        'booking_code'   => 'BK-20260101-TEST',
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
        ->delete('/api/vehicles/1');

    // Must NOT crash with 500. Must be graceful 4xx.
    $statusCode = $response->getStatusCode();
    expect($statusCode)->not->toBe(500);
    expect($statusCode)->toBeGreaterThanOrEqual(400);
    expect($statusCode)->toBeLessThan(500);

    // Vehicle must still exist in DB
    $count = $db->table('vehicles')->where('id', 1)->countAllResults();
    expect($count)->toBe(1);
});

<?php

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\JwtService;
use Config\Database;

uses(FeatureTestTrait::class, DatabaseTestTrait::class);

beforeEach(function () {
    putenv('jwt.secret=test-secret-key-user-controller');

    $db = Database::connect();

    // Clean up tables
    $db->table('activity_logs')->emptyTable();
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
            'name'           => 'Approver Level 1',
            'email'          => 'approver1@test.com',
            'password'       => password_hash('password123', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 1,
            'created_at'     => $now,
        ],
        [
            'id'             => 3,
            'name'           => 'Approver Level 2',
            'email'          => 'approver2@test.com',
            'password'       => password_hash('password123', PASSWORD_DEFAULT),
            'role'           => 'approver',
            'approval_level' => 2,
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

// -----------------------------------------------------------------------------
// 1. GET /api/users
// -----------------------------------------------------------------------------
test('GET /api/users list response does not contain password field for any user', function () {
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
                     ->get('api/users');

    $response->assertStatus(200);
    $json = json_decode($response->getJSON(), true);

    expect($json['status'])->toBeTrue();
    expect($json['data'])->toBeArray();
    expect(count($json['data']))->toBeGreaterThanOrEqual(3);

    foreach ($json['data'] as $user) {
        expect($user)->not->toHaveKey('password');
    }
});

// -----------------------------------------------------------------------------
// 2. POST /api/users (Create)
// -----------------------------------------------------------------------------
test('POST /api/users returns 422 when role is approver without approval_level', function () {
    $payload = [
        'name'     => 'New Approver',
        'email'    => 'new_approver@test.com',
        'password' => 'password123',
        'role'     => 'approver',
    ];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->post('api/users');

    $response->assertStatus(422);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('approval_level');
});

test('POST /api/users returns 422 when role is approver with invalid approval_level', function () {
    $payload = [
        'name'           => 'New Approver Invalid Level',
        'email'          => 'invalid_approver@test.com',
        'password'       => 'password123',
        'role'           => 'approver',
        'approval_level' => 3,
    ];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->post('api/users');

    $response->assertStatus(422);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('approval_level');
});

test('POST /api/users returns 422 when role is admin with non-null approval_level', function () {
    $payload = [
        'name'           => 'New Admin Invalid',
        'email'          => 'invalid_admin@test.com',
        'password'       => 'password123',
        'role'           => 'admin',
        'approval_level' => 1,
    ];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->post('api/users');

    $response->assertStatus(422);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('approval_level');
});

test('POST /api/users returns 422 when email is duplicate', function () {
    $payload = [
        'name'     => 'Duplicate Email User',
        'email'    => 'admin@test.com', // Duplicate
        'password' => 'password123',
        'role'     => 'admin',
    ];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->post('api/users');

    $response->assertStatus(422);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('email');
});

test('POST /api/users returns 422 when password is less than 6 characters', function () {
    $payload = [
        'name'     => 'Short Password User',
        'email'    => 'short_pwd@test.com',
        'password' => '12345', // < 6 chars
        'role'     => 'admin',
    ];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->post('api/users');

    $response->assertStatus(422);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('password');
});

test('POST /api/users succeeds (201) with valid payloads and response does not contain password field', function () {
    // 1. Valid Admin
    $resAdmin = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode([
        'name'     => 'Admin Dua',
        'email'    => 'admin2@test.com',
        'password' => 'password123',
        'role'     => 'admin',
    ]))->post('api/users');

    $resAdmin->assertStatus(201);
    $jsonAdmin = json_decode($resAdmin->getJSON(), true);
    expect($jsonAdmin['status'])->toBeTrue();
    expect($jsonAdmin['data'])->not->toHaveKey('password');

    // 2. Valid Approver Level 1
    $resL1 = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode([
        'name'           => 'Approver Baru L1',
        'email'          => 'approver_new_l1@test.com',
        'password'       => 'password123',
        'role'           => 'approver',
        'approval_level' => 1,
    ]))->post('api/users');

    $resL1->assertStatus(201);
    $jsonL1 = json_decode($resL1->getJSON(), true);
    expect($jsonL1['status'])->toBeTrue();
    expect($jsonL1['data'])->not->toHaveKey('password');

    // 3. Valid Approver Level 2
    $resL2 = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode([
        'name'           => 'Approver Baru L2',
        'email'          => 'approver_new_l2@test.com',
        'password'       => 'password123',
        'role'           => 'approver',
        'approval_level' => 2,
    ]))->post('api/users');

    $resL2->assertStatus(201);
    $jsonL2 = json_decode($resL2->getJSON(), true);
    expect($jsonL2['status'])->toBeTrue();
    expect($jsonL2['data'])->not->toHaveKey('password');
});

// -----------------------------------------------------------------------------
// 3. PUT /api/users/(:num) (Update)
// -----------------------------------------------------------------------------
test('PUT /api/users/(:num) returns 422 when updating role from admin to approver without approval_level', function () {
    // User 1 is admin
    $payload = [
        'role' => 'approver',
    ];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->put('api/users/1');

    $response->assertStatus(422);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('approval_level');
});

test('PUT /api/users/(:num) returns 422 when updating role from approver to admin but approval_level is provided', function () {
    // User 2 is approver
    $payload = [
        'role'           => 'admin',
        'approval_level' => 1,
    ];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->put('api/users/2');

    $response->assertStatus(422);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('approval_level');
});

test('PUT /api/users/(:num) returns 422 when updating approval_level to invalid value for approver', function () {
    // User 2 is approver
    $payload = [
        'approval_level' => 0,
    ];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->put('api/users/2');

    $response->assertStatus(422);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['errors'])->toHaveKey('approval_level');
});

test('PUT /api/users/(:num) partial update succeeds (200) and other fields remain unchanged', function () {
    // User 2: Approver Level 1
    $payload = [
        'name' => 'Approver Level 1 Updated Name',
    ];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->put('api/users/2');

    $response->assertStatus(200);
    $json = json_decode($response->getJSON(), true);

    expect($json['status'])->toBeTrue();
    expect($json['data']['name'])->toBe('Approver Level 1 Updated Name');
    expect($json['data']['email'])->toBe('approver1@test.com');
    expect($json['data']['role'])->toBe('approver');
    expect((int)$json['data']['approval_level'])->toBe(1);
    expect($json['data'])->not->toHaveKey('password');
});

test('PUT /api/users/(:num) returns 404 when user id does not exist', function () {
    $payload = [
        'name' => 'Ghost User',
    ];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->adminToken}",
        'Content-Type'  => 'application/json',
    ])->withBody(json_encode($payload))->put('api/users/9999');

    $response->assertStatus(404);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['message'])->toBe('User not found');
});

// -----------------------------------------------------------------------------
// 4. DELETE /api/users/(:num) (Delete)
// -----------------------------------------------------------------------------
test('DELETE /api/users/(:num) returns 400 when admin attempts self-deletion', function () {
    // User 1 is current logged in admin
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
                     ->delete('api/users/1');

    $response->assertStatus(400);
    $json = json_decode($response->getJSON(), true);

    expect($json['status'])->toBeFalse();
    expect($json['message'])->toBe('Cannot delete your own account');
});

test('DELETE /api/users/(:num) succeeds (200) and deletes other user from database', function () {
    // Delete User 3 (Approver Level 2)
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
                     ->delete('api/users/3');

    $response->assertStatus(200);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeTrue();

    // Verify deleted in DB
    $db = Database::connect();
    $deleted = $db->table('users')->where('id', 3)->get()->getRowArray();
    expect($deleted)->toBeNull();
});

test('DELETE /api/users/(:num) returns 404 when deleting non-existent user id', function () {
    $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
                     ->delete('api/users/9999');

    $response->assertStatus(404);
    $json = json_decode($response->getJSON(), true);
    expect($json['status'])->toBeFalse();
    expect($json['message'])->toBe('User not found');
});

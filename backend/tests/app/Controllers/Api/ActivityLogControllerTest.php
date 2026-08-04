<?php

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\JwtService;
use Config\Database;

uses(FeatureTestTrait::class, DatabaseTestTrait::class);

beforeEach(function () {
    putenv('jwt.secret=test-secret-key-activity-log-controller');

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
    ]);

    $jwtService = new JwtService();
    $this->adminToken = $jwtService->generate([
        'id'             => 1,
        'name'           => 'Admin Utama',
        'email'          => 'admin@test.com',
        'role'           => 'admin',
        'approval_level' => null,
    ]);

    $this->approverToken = $jwtService->generate([
        'id'             => 2,
        'name'           => 'Approver Level 1',
        'email'          => 'approver1@test.com',
        'role'           => 'approver',
        'approval_level' => 1,
    ]);
});

// =============================================================================
// GET /api/activity-logs (Auth & Filtering & Pagination)
// =============================================================================

it('GET /api/activity-logs returns 403 when accessed by approver role', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->approverToken])
        ->get('/api/activity-logs');

    $response->assertStatus(403);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(false);
    expect($body['message'])->toBe('Forbidden');
});

it('GET /api/activity-logs filters strictly by entity_type parameter', function () {
    $db = Database::connect();
    $now = date('Y-m-d H:i:s');

    $db->table('activity_logs')->insertBatch([
        [
            'user_id'     => 1,
            'action'      => 'user.created',
            'entity_type' => 'user',
            'entity_id'   => 1,
            'description' => 'User created log',
            'ip_address'  => '127.0.0.1',
            'created_at'  => $now,
        ],
        [
            'user_id'     => 1,
            'action'      => 'vehicle.created',
            'entity_type' => 'vehicle',
            'entity_id'   => 1,
            'description' => 'Vehicle created log',
            'ip_address'  => '127.0.0.1',
            'created_at'  => $now,
        ],
    ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/activity-logs?entity_type=vehicle');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(true);
    expect($body['data']['logs'])->toHaveCount(1);
    expect($body['data']['logs'][0]['entity_type'])->toBe('vehicle');
    expect($body['data']['logs'][0]['action'])->toBe('vehicle.created');
});

it('GET /api/activity-logs filters logs within start_date and end_date range', function () {
    $db = Database::connect();

    $db->table('activity_logs')->insertBatch([
        [
            'user_id'     => 1,
            'action'      => 'user.login',
            'entity_type' => 'user',
            'entity_id'   => 1,
            'description' => 'Login in Sept',
            'ip_address'  => '127.0.0.1',
            'created_at'  => '2026-09-15 10:00:00',
        ],
        [
            'user_id'     => 1,
            'action'      => 'user.login',
            'entity_type' => 'user',
            'entity_id'   => 1,
            'description' => 'Login in Oct',
            'ip_address'  => '127.0.0.1',
            'created_at'  => '2026-10-15 10:00:00',
        ],
    ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/activity-logs?start_date=2026-09-01&end_date=2026-09-30');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(true);
    expect($body['data']['logs'])->toHaveCount(1);
    expect($body['data']['logs'][0]['description'])->toBe('Login in Sept');
});

it('GET /api/activity-logs handles pagination accurately across >50 records', function () {
    $db = Database::connect();
    $totalRecords = 65;

    $batch = [];
    for ($i = 1; $i <= $totalRecords; $i++) {
        // Create distinct timestamps so ordering by created_at DESC is deterministic
        $time = date('Y-m-d H:i:s', strtotime("2026-09-01 00:00:00 +{$i} seconds"));
        $batch[] = [
            'user_id'     => 1,
            'action'      => 'booking.created',
            'entity_type' => 'booking',
            'entity_id'   => $i,
            'description' => "Booking creation log #{$i}",
            'ip_address'  => '127.0.0.1',
            'created_at'  => $time,
        ];
    }
    $db->table('activity_logs')->insertBatch($batch);

    // Page 1
    $res1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/activity-logs?page=1');
    $res1->assertStatus(200);
    $body1 = json_decode($res1->getJSON(), true);

    expect($body1['data']['pagination']['total'])->toBe($totalRecords);
    expect($body1['data']['pagination']['page'])->toBe(1);
    expect($body1['data']['pagination']['limit'])->toBe(50);
    expect($body1['data']['pagination']['total_pages'])->toBe(2);
    expect($body1['data']['logs'])->toHaveCount(50);

    // Page 2
    $res2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/activity-logs?page=2');
    $res2->assertStatus(200);
    $body2 = json_decode($res2->getJSON(), true);

    expect($body2['data']['pagination']['page'])->toBe(2);
    expect($body2['data']['logs'])->toHaveCount(15);

    // Assert no duplicate or missing IDs between Page 1 and Page 2
    $page1Ids = array_column($body1['data']['logs'], 'id');
    $page2Ids = array_column($body2['data']['logs'], 'id');

    $intersect = array_intersect($page1Ids, $page2Ids);
    expect($intersect)->toBeEmpty();

    $combinedIds = array_unique(array_merge($page1Ids, $page2Ids));
    expect(count($combinedIds))->toBe($totalRecords);
});

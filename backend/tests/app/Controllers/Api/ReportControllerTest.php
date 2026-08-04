<?php

use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\JwtService;
use Config\Database;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(FeatureTestTrait::class, DatabaseTestTrait::class);

beforeEach(function () {
    putenv('jwt.secret=test-secret-key-report-controller');

    $db = Database::connect();

    // Clean up tables in order
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

    // Seed 3 bookings with different dates and statuses:
    // Booking 1: September 2026, status = waiting_level_1
    // Booking 2: September 2026, status = approved
    // Booking 3: October 2026, status = waiting_level_1 (outside Sept filter)
    $db->table('bookings')->insertBatch([
        [
            'id'             => 1,
            'booking_code'   => 'BK-20260901-AAAA',
            'admin_id'       => 1,
            'vehicle_id'     => 1,
            'driver_id'      => 1,
            'requester_name' => 'Pemohon A',
            'purpose'        => 'Dinas Jakarta',
            'destination'    => 'Kantor Pusat',
            'start_date'     => '2026-09-01 08:00:00',
            'end_date'       => '2026-09-05 17:00:00',
            'status'         => 'waiting_level_1',
            'created_at'     => '2026-09-01 07:00:00',
        ],
        [
            'id'             => 2,
            'booking_code'   => 'BK-20260910-BBBB',
            'admin_id'       => 1,
            'vehicle_id'     => 1,
            'driver_id'      => 1,
            'requester_name' => 'Pemohon B',
            'purpose'        => 'Survei Lapangan',
            'destination'    => 'Bandung',
            'start_date'     => '2026-09-10 08:00:00',
            'end_date'       => '2026-09-12 17:00:00',
            'status'         => 'approved',
            'created_at'     => '2026-09-10 07:00:00',
        ],
        [
            'id'             => 3,
            'booking_code'   => 'BK-20261001-CCCC',
            'admin_id'       => 1,
            'vehicle_id'     => 1,
            'driver_id'      => 1,
            'requester_name' => 'Pemohon C',
            'purpose'        => 'Kunjungan Kerja',
            'destination'    => 'Surabaya',
            'start_date'     => '2026-10-01 08:00:00',
            'end_date'       => '2026-10-05 17:00:00',
            'status'         => 'waiting_level_1',
            'created_at'     => '2026-10-01 07:00:00',
        ],
    ]);

    $db->table('booking_approvals')->insertBatch([
        [
            'booking_id'  => 1,
            'approver_id' => 2,
            'level'       => 1,
            'status'      => 'pending',
        ],
        [
            'booking_id'  => 1,
            'approver_id' => 3,
            'level'       => 2,
            'status'      => 'pending',
        ],
        [
            'booking_id'  => 2,
            'approver_id' => 2,
            'level'       => 1,
            'status'      => 'approved',
        ],
        [
            'booking_id'  => 2,
            'approver_id' => 3,
            'level'       => 2,
            'status'      => 'approved',
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
// GET /api/reports (JSON)
// =============================================================================

it('GET /api/reports returns 403 when accessed by approver role', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->approverToken])
        ->get('/api/reports?start_date=2026-09-01&end_date=2026-09-30');

    $response->assertStatus(403);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(false);
    expect($body['message'])->toBe('Forbidden');
});

it('GET /api/reports returns 422 when start_date or end_date is missing', function () {
    // Missing start_date
    $res1 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/reports?end_date=2026-09-30');
    $res1->assertStatus(422);
    $body1 = json_decode($res1->getJSON(), true);
    expect($body1['status'])->toBe(false);
    expect($body1['errors'])->toHaveKey('start_date');

    // Missing end_date
    $res2 = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/reports?start_date=2026-09-01');
    $res2->assertStatus(422);
    $body2 = json_decode($res2->getJSON(), true);
    expect($body2['status'])->toBe(false);
    expect($body2['errors'])->toHaveKey('end_date');
});

it('GET /api/reports filters bookings within date range correctly', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/reports?start_date=2026-09-01&end_date=2026-09-30');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(true);
    // Booking 1 and 2 are in Sept 2026; Booking 3 is in Oct 2026
    expect($body['data'])->toHaveCount(2);

    $codes = array_column($body['data'], 'booking_code');
    expect($codes)->toContain('BK-20260901-AAAA');
    expect($codes)->toContain('BK-20260910-BBBB');
    expect($codes)->not()->toContain('BK-20261001-CCCC');
});

it('GET /api/reports filters by status when status parameter is supplied', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/reports?start_date=2026-09-01&end_date=2026-09-30&status=approved');

    $response->assertStatus(200);
    $body = json_decode($response->getJSON(), true);
    expect($body['status'])->toBe(true);
    expect($body['data'])->toHaveCount(1);
    expect($body['data'][0]['booking_code'])->toBe('BK-20260910-BBBB');
    expect($body['data'][0]['status'])->toBe('approved');
});

// =============================================================================
// GET /api/reports/export (.xlsx)
// =============================================================================

it('GET /api/reports/export returns 403 when accessed by approver role', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->approverToken])
        ->get('/api/reports/export?start_date=2026-09-01&end_date=2026-09-30');

    $response->assertStatus(403);
});

it('GET /api/reports/export returns 422 when dates are missing', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/reports/export');

    $response->assertStatus(422);
});

it('GET /api/reports/export returns xlsx binary stream and valid parsed content', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/reports/export?start_date=2026-09-01&end_date=2026-09-30');

    $response->assertStatus(200);
    $contentType = $response->getHeaderLine('Content-Type');
    expect($contentType)->toContain('spreadsheetml.sheet');

    $content = $response->getBody();
    expect($content)->not()->toBeEmpty();

    // Save binary stream to a temp file and parse using PhpSpreadsheet
    $tempFile = sys_get_temp_dir() . '/test_export_' . uniqid() . '.xlsx';
    file_put_contents($tempFile, $content);

    try {
        $spreadsheet = IOFactory::load($tempFile);
        $sheet = $spreadsheet->getActiveSheet();

        // Row 1: Header row (No, Booking Code, Requester, etc.)
        expect($sheet->getCell('A1')->getValue())->toBe('No');
        expect($sheet->getCell('B1')->getValue())->toBe('Booking Code');
        expect($sheet->getCell('C1')->getValue())->toBe('Requester');
        expect($sheet->getCell('K1')->getValue())->toBe('Status');

        // Row 2: Booking 1 (BK-20260901-AAAA)
        expect($sheet->getCell('A2')->getValue())->toEqual(1);
        expect($sheet->getCell('B2')->getValue())->toBe('BK-20260901-AAAA');
        expect($sheet->getCell('C2')->getValue())->toBe('Pemohon A');
        expect($sheet->getCell('K2')->getValue())->toBe('waiting_level_1');

        // Row 3: Booking 2 (BK-20260910-BBBB)
        expect($sheet->getCell('A3')->getValue())->toEqual(2);
        expect($sheet->getCell('B3')->getValue())->toBe('BK-20260910-BBBB');
        expect($sheet->getCell('C3')->getValue())->toBe('Pemohon B');
        expect($sheet->getCell('K3')->getValue())->toBe('approved');

        // Row 4: Empty (only 2 bookings in Sept 2026)
        expect($sheet->getCell('B4')->getValue())->toBeNull();
    } finally {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
});

it('GET /api/reports/export supports status filtering in binary output', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->get('/api/reports/export?start_date=2026-09-01&end_date=2026-09-30&status=approved');

    $response->assertStatus(200);

    $content = $response->getBody();
    $tempFile = sys_get_temp_dir() . '/test_export_' . uniqid() . '.xlsx';
    file_put_contents($tempFile, $content);

    try {
        $spreadsheet = IOFactory::load($tempFile);
        $sheet = $spreadsheet->getActiveSheet();

        // Only 1 row of data (Row 2) for status=approved
        expect($sheet->getCell('B2')->getValue())->toBe('BK-20260910-BBBB');
        expect($sheet->getCell('K2')->getValue())->toBe('approved');
        expect($sheet->getCell('B3')->getValue())->toBeNull();
    } finally {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
});

<?php
// app/Database/Seeds/DriverSeeder.php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('drivers')->insertBatch([
            [
                'name'           => 'Budi Santoso',
                'license_number' => 'SIM-A-0012345',
                'phone'          => '+6281234567890',
                'status'         => 'active',
                'created_at'     => $now,
            ],
            [
                'name'           => 'Eko Prasetyo',
                'license_number' => 'SIM-A-0023456',
                'phone'          => '+6281345678901',
                'status'         => 'active',
                'created_at'     => $now,
            ],
            [
                'name'           => 'Agus Widodo',
                'license_number' => 'SIM-B1-0034567',
                'phone'          => '+6281456789012',
                'status'         => 'active',
                'created_at'     => $now,
            ],
            [
                'name'           => 'Hendra Kusuma',
                'license_number' => 'SIM-B2-0045678',
                'phone'          => '+6281567890123',
                'status'         => 'inactive',
                'created_at'     => $now,
            ],
        ]);
    }
}

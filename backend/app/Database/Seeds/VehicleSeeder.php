<?php
// app/Database/Seeds/VehicleSeeder.php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('vehicles')->insertBatch([
            [
                'name'         => 'Toyota Fortuner',
                'plate_number' => 'BG 1234 ABC',
                'type'         => 'passenger',
                'ownership'    => 'own',
                'region'       => 'Sulawesi Tenggara',
                'status'       => 'available',
                'created_at'   => $now,
            ],
            [
                'name'         => 'Mitsubishi L300',
                'plate_number' => 'BG 5678 DEF',
                'type'         => 'cargo',
                'ownership'    => 'own',
                'region'       => 'Sulawesi Tenggara',
                'status'       => 'available',
                'created_at'   => $now,
            ],
            [
                'name'         => 'Toyota Hiace',
                'plate_number' => 'BG 9012 GHI',
                'type'         => 'passenger',
                'ownership'    => 'rental',
                'region'       => 'Sulawesi Selatan',
                'status'       => 'available',
                'created_at'   => $now,
            ],
            [
                'name'         => 'Isuzu Elf',
                'plate_number' => 'BG 3456 JKL',
                'type'         => 'passenger',
                'ownership'    => 'rental',
                'region'       => 'Sulawesi Tengah',
                'status'       => 'available',
                'created_at'   => $now,
            ],
            [
                'name'         => 'Hino Dutro',
                'plate_number' => 'BG 7890 MNO',
                'type'         => 'cargo',
                'ownership'    => 'own',
                'region'       => 'Sulawesi Tenggara',
                'status'       => 'maintenance',
                'created_at'   => $now,
            ],
            [
                'name'         => 'Mitsubishi Canter',
                'plate_number' => 'BG 2345 PQR',
                'type'         => 'cargo',
                'ownership'    => 'rental',
                'region'       => 'Sulawesi Selatan',
                'status'       => 'inactive',
                'created_at'   => $now,
            ],
        ]);
    }
}

<?php
// app/Database/Seeds/UserSeeder.php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('users')->insertBatch([
            [
                'name'           => 'Admin Utama',
                'email'          => 'admin@vehicle.com',
                'password'       => password_hash('password', PASSWORD_DEFAULT),
                'role'           => 'admin',
                'approval_level' => null,
                'created_at'     => $now,
            ],
            [
                'name'           => 'Approver Level 1',
                'email'          => 'approver1@vehicle.com',
                'password'       => password_hash('password', PASSWORD_DEFAULT),
                'role'           => 'approver',
                'approval_level' => 1,
                'created_at'     => $now,
            ],
            [
                'name'           => 'Approver Level 2',
                'email'          => 'approver2@vehicle.com',
                'password'       => password_hash('password', PASSWORD_DEFAULT),
                'role'           => 'approver',
                'approval_level' => 2,
                'created_at'     => $now,
            ],
        ]);
    }
}

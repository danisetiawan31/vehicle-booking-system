<?php
// app/Database/Seeds/MainSeeder.php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MainSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('UserSeeder');
        $this->call('VehicleSeeder');
        $this->call('DriverSeeder');
        $this->call('BookingSeeder');
    }
}

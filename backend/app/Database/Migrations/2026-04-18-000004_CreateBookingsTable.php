<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBookingsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'booking_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ],
            'admin_id' => [
                'type'     => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null'     => false,
            ],
            'vehicle_id' => [
                'type'     => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null'     => false,
            ],
            'driver_id' => [
                'type'     => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null'     => false,
            ],
            'requester_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'purpose' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'destination' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'start_date' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'end_date' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'status' => [
                'type'    => "ENUM('waiting_level_1','waiting_level_2','approved','rejected')",
                'null'    => false,
                'default' => 'waiting_level_1',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('booking_code');

        $this->forge->addForeignKey('admin_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('vehicle_id', 'vehicles', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('driver_id', 'drivers', 'id', 'RESTRICT', 'RESTRICT');

        $this->forge->createTable('bookings');
    }

    public function down(): void
    {
        $this->forge->dropTable('bookings', true);
    }
}

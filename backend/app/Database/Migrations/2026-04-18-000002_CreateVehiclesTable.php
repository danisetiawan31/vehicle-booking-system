<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVehiclesTable extends Migration
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
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'plate_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ],
            'type' => [
                'type' => "ENUM('passenger','cargo')",
                'null' => false,
            ],
            'ownership' => [
                'type' => "ENUM('own','rental')",
                'null' => false,
            ],
            'region' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'status' => [
                'type'    => "ENUM('available','maintenance','inactive')",
                'null'    => false,
                'default' => 'available',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('plate_number');

        $this->forge->createTable('vehicles');
    }

    public function down(): void
    {
        $this->forge->dropTable('vehicles', true);
    }
}

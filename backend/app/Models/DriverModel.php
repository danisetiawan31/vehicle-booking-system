<?php
// app/Models/DriverModel.php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class DriverModel extends Model
{
    protected $table            = 'drivers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'name',
        'license_number',
        'phone',
        'status',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
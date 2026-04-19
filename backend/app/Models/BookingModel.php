<?php
// app/Models/BookingModel.php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class BookingModel extends Model
{
    protected $table            = 'bookings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'booking_code',
        'admin_id',
        'vehicle_id',
        'driver_id',
        'requester_name',
        'purpose',
        'destination',
        'start_date',
        'end_date',
        'status',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
<?php
// app/Models/BookingApprovalModel.php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class BookingApprovalModel extends Model
{
    protected $table            = 'booking_approvals';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'booking_id',
        'approver_id',
        'level',
        'status',
        'notes',
        'acted_at',
    ];

    // Dates
    protected $useTimestamps = false;
}
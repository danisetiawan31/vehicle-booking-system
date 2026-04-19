<?php
// app/Services/ActivityLogService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLogModel;
use Throwable;

class ActivityLogService
{
    public function log(int $userId, string $action, string $entityType, int $entityId, string $description, string $ipAddress): void
    {
        try {
            $model = new ActivityLogModel();
            $model->insert([
                'user_id'     => $userId,
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'description' => $description,
                'ip_address'  => $ipAddress,
            ]);
        } catch (Throwable $e) {
            // Silent failure
        }
    }
}

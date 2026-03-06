<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Registrar un evento de auditoría.
     */
    public function log(
        int $restaurantId,
        string $entityType,
        int $entityId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        return AuditLog::create([
            'restaurant_id' => $restaurantId,
            'user_id'       => Auth::id(),
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'action'        => $action,
            'old_values'    => $oldValues,
            'new_values'    => $newValues,
            'created_at'    => now(),
        ]);
    }

    /**
     * Prevefined actions.
     */
    public const ACTION_CREATED          = 'created';
    public const ACTION_UPDATED          = 'updated';
    public const ACTION_DELETED          = 'deleted';
    public const ACTION_STATUS_CHANGED   = 'status_changed';
    public const ACTION_PAID             = 'paid';
    public const ACTION_CANCELLED        = 'cancelled';
    public const ACTION_CLOSED           = 'closed';
    public const ACTION_DISCOUNT_APPLIED = 'discount_applied';
    public const ACTION_OPENED           = 'opened';
}

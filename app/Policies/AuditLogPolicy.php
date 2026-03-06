<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general']);
    }
}

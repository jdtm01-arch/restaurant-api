<?php

namespace App\Policies;

use App\Models\FinancialMovement;
use App\Models\User;

class FinancialMovementPolicy
{
    /**
     * Ver listado de movimientos financieros.
     * Acceso: admin_restaurante, admin_general, caja.
     */
    public function viewAny(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja']);
    }
}

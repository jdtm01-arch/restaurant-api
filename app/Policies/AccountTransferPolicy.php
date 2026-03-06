<?php

namespace App\Policies;

use App\Models\AccountTransfer;
use App\Models\User;

class AccountTransferPolicy
{
    /**
     * Ver listado de transferencias.
     * Acceso: admin_restaurante, admin_general, caja.
     */
    public function viewAny(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja']);
    }

    /**
     * Crear transferencia.
     * Solo admin_restaurante, admin_general, caja.
     */
    public function create(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja']);
    }

    /**
     * Actualizar transferencia.
     * Solo admin_restaurante y admin_general.
     */
    public function update(User $user, AccountTransfer $transfer): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $transfer->restaurant_id)
            || $user->hasRoleInRestaurant('admin_general', $transfer->restaurant_id);
    }

    /**
     * Eliminar transferencia.
     * Solo admin_general.
     */
    public function delete(User $user, AccountTransfer $transfer): bool
    {
        return $user->hasRoleInRestaurant('admin_general', $transfer->restaurant_id);
    }
}

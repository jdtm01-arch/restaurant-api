<?php

namespace App\Policies;

use App\Models\FinancialAccount;
use App\Models\User;

class FinancialAccountPolicy
{
    /**
     * Ver listado de cuentas financieras.
     * Acceso: admin_restaurante, admin_general, caja.
     */
    public function viewAny(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja']);
    }

    /**
     * Ver una cuenta específica.
     */
    public function view(User $user, FinancialAccount $account): bool
    {
        $role = $user->roleForRestaurant($account->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja']);
    }

    /**
     * Crear cuenta financiera.
     * Solo admin_restaurante y admin_general.
     */
    public function create(User $user, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    /**
     * Actualizar cuenta financiera.
     */
    public function update(User $user, FinancialAccount $account): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $account->restaurant_id)
            || $user->hasRoleInRestaurant('admin_general', $account->restaurant_id);
    }

    /**
     * Eliminar cuenta financiera.
     */
    public function delete(User $user, FinancialAccount $account): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $account->restaurant_id)
            || $user->hasRoleInRestaurant('admin_general', $account->restaurant_id);
    }
}

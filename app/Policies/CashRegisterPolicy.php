<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CashRegister;

class CashRegisterPolicy
{
    public function viewAny(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja', 'mozo']);
    }

    public function view(User $user, CashRegister $register): bool
    {
        $role = $user->roleForRestaurant($register->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja', 'mozo']);
    }

    public function open(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja']);
    }

    public function close(User $user, CashRegister $register): bool
    {
        $role = $user->roleForRestaurant($register->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja']);
    }

    public function xReport(User $user, CashRegister $register): bool
    {
        return $this->view($user, $register);
    }
}

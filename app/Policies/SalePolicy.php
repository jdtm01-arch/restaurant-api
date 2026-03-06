<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja']);
    }

    public function view(User $user, Sale $sale): bool
    {
        $role = $user->roleForRestaurant($sale->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja']);
    }

    public function receipt(User $user, Sale $sale): bool
    {
        $role = $user->roleForRestaurant($sale->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general', 'caja', 'mozo']);
    }
}

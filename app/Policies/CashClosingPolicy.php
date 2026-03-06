<?php

namespace App\Policies;

use App\Models\CashClosing;
use App\Models\User;

class CashClosingPolicy
{
    private const ALLOWED_ROLES = ['admin_restaurante', 'admin_general'];

    public function viewAny(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, self::ALLOWED_ROLES);
    }

    public function view(User $user, CashClosing $closing): bool
    {
        $role = $user->roleForRestaurant($closing->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, self::ALLOWED_ROLES);
    }

    public function create(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, self::ALLOWED_ROLES);
    }
}

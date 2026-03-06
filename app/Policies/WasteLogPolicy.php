<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WasteLog;

class WasteLogPolicy
{
    private const ALLOWED_ROLES = ['admin_restaurante', 'admin_general', 'cocina'];

    public function viewAny(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, self::ALLOWED_ROLES);
    }

    public function view(User $user, WasteLog $wasteLog): bool
    {
        $role = $user->roleForRestaurant($wasteLog->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, self::ALLOWED_ROLES);
    }

    public function create(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, self::ALLOWED_ROLES);
    }

    public function update(User $user, WasteLog $wasteLog): bool
    {
        $role = $user->roleForRestaurant($wasteLog->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, self::ALLOWED_ROLES);
    }

    public function delete(User $user, WasteLog $wasteLog): bool
    {
        $role = $user->roleForRestaurant($wasteLog->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, ['admin_restaurante', 'admin_general']);
    }
}

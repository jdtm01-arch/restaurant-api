<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Supplier;

class SupplierPolicy
{
    public function viewAny(User $user, int $restaurantId): bool
    {
        return $user->roleForRestaurant($restaurantId) !== null;
    }

    public function view(User $user, Supplier $supplier): bool
    {
        // Suppliers are global, just check user is authenticated
        return true;
    }

    public function create(User $user, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function update(User $user, Supplier $supplier, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function delete(User $user, Supplier $supplier, int $restaurantId): bool
    {
        return $this->update($user, $supplier, $restaurantId);
    }
}

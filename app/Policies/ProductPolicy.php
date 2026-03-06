<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user, int $restaurantId): bool
    {
        return $user->roleForRestaurant($restaurantId) !== null;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->roleForRestaurant($product->restaurant_id) !== null;
    }

    public function create(User $user, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $product->restaurant_id)
            || $user->hasRoleInRestaurant('admin_general', $product->restaurant_id);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}

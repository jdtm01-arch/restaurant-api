<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PaymentMethod;

class PaymentMethodPolicy
{
    /**
     * PaymentMethods are global (no restaurant_id), but only admins can manage them.
     */
    public function viewAny(User $user, int $restaurantId): bool
    {
        return $user->roleForRestaurant($restaurantId) !== null;
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return true;
    }

    public function create(User $user, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function update(User $user, PaymentMethod $paymentMethod, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function delete(User $user, PaymentMethod $paymentMethod, int $restaurantId): bool
    {
        return $this->update($user, $paymentMethod, $restaurantId);
    }
}

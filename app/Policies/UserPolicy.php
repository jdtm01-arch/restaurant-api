<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Only admin_general and admin_restaurante can manage users.
     */
    public function viewAny(User $user, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function update(User $user, User $model, int $restaurantId): bool
    {
        if ($this->isRestaurantAdminActingOnGeneralAdmin($user, $model, $restaurantId)) {
            return false;
        }

        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function delete(User $user, User $model, int $restaurantId): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        if ($this->isRestaurantAdminActingOnGeneralAdmin($user, $model, $restaurantId)) {
            return false;
        }

        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function resetPassword(User $user, User $model, int $restaurantId): bool
    {
        if ($this->isRestaurantAdminActingOnGeneralAdmin($user, $model, $restaurantId)) {
            return false;
        }

        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    /**
     * Returns true when an admin_restaurante is trying to act on an admin_general.
     */
    private function isRestaurantAdminActingOnGeneralAdmin(User $actor, User $target, int $restaurantId): bool
    {
        if (! $actor->hasRoleInRestaurant('admin_restaurante', $restaurantId)) {
            return false;
        }

        return $target->hasRoleInRestaurant('admin_general', $restaurantId);
    }
}

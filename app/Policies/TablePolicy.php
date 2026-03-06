<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Table;

class TablePolicy
{
    public function viewAny(User $user, int $restaurantId): bool
    {
        return $user->roleForRestaurant($restaurantId) !== null;
    }

    public function view(User $user, Table $table): bool
    {
        return $user->roleForRestaurant($table->restaurant_id) !== null;
    }

    public function create(User $user, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function update(User $user, Table $table): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $table->restaurant_id)
            || $user->hasRoleInRestaurant('admin_general', $table->restaurant_id);
    }

    public function delete(User $user, Table $table): bool
    {
        return $this->update($user, $table);
    }

    public function restore(User $user, Table $table): bool
    {
        return $this->update($user, $table);
    }
}

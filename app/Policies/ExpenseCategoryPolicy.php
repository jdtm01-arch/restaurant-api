<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ExpenseCategory;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user, int $restaurantId): bool
    {
        return $user->roleForRestaurant($restaurantId) !== null;
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $user->roleForRestaurant($category->restaurant_id) !== null;
    }

    public function create(User $user, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $category->restaurant_id)
            || $user->hasRoleInRestaurant('admin_general', $category->restaurant_id);
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $this->update($user, $category);
    }
}

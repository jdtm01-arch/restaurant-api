<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Expense;

class ExpensePolicy
{
    /*
    |--------------------------------------------------------------------------
    | View Any (listar)
    |--------------------------------------------------------------------------
    */
    public function viewAny(User $user, int $restaurantId): bool
    {
        return $user->roleForRestaurant($restaurantId) !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | View (ver uno específico)
    |--------------------------------------------------------------------------
    */
    public function view(User $user, Expense $expense): bool
    {
        return $user->roleForRestaurant($expense->restaurant_id) !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */
    public function create(User $user, int $restaurantId): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $restaurantId)
            || $user->hasRoleInRestaurant('admin_general', $restaurantId);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */
    public function update(User $user, Expense $expense): bool
    {
        return $user->hasRoleInRestaurant('admin_restaurante', $expense->restaurant_id)
            || $user->hasRoleInRestaurant('admin_general', $expense->restaurant_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */
    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }
}
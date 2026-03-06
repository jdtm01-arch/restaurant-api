<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Roles con acceso completo al módulo de órdenes.
     */
    private const ADMIN_ROLES = ['admin_restaurante', 'admin_general'];

    /**
     * Roles que pueden ver órdenes.
     */
    private const VIEW_ROLES = ['admin_restaurante', 'admin_general', 'caja', 'mozo', 'cocina'];

    /**
     * Roles que pueden crear y gestionar ítems.
     */
    private const MANAGE_ROLES = ['admin_restaurante', 'admin_general', 'caja', 'mozo'];

    public function viewAny(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, self::VIEW_ROLES);
    }

    public function view(User $user, Order $order): bool
    {
        $role = $user->roleForRestaurant($order->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, self::VIEW_ROLES);
    }

    public function create(User $user, int $restaurantId): bool
    {
        $role = $user->roleForRestaurant($restaurantId);
        if (! $role) return false;

        return in_array($role->slug, self::MANAGE_ROLES);
    }

    public function update(User $user, Order $order): bool
    {
        $role = $user->roleForRestaurant($order->restaurant_id);
        if (! $role) return false;

        if (! in_array($role->slug, self::MANAGE_ROLES)) return false;

        // Mozo can only modify their own orders
        if ($role->slug === 'mozo' && $order->user_id !== $user->id) return false;

        return true;
    }

    public function applyDiscount(User $user, Order $order): bool
    {
        $role = $user->roleForRestaurant($order->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, self::ADMIN_ROLES);
    }

    public function close(User $user, Order $order): bool
    {
        $role = $user->roleForRestaurant($order->restaurant_id);
        if (! $role) return false;

        if (! in_array($role->slug, self::MANAGE_ROLES)) return false;

        // Mozo can only close/reopen their own orders
        if ($role->slug === 'mozo' && $order->user_id !== $user->id) return false;

        return true;
    }

    public function cancel(User $user, Order $order): bool
    {
        $role = $user->roleForRestaurant($order->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, self::ADMIN_ROLES);
    }

    public function kitchenTicket(User $user, Order $order): bool
    {
        $role = $user->roleForRestaurant($order->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, self::VIEW_ROLES);
    }

    public function pay(User $user, Order $order): bool
    {
        $role = $user->roleForRestaurant($order->restaurant_id);
        if (! $role) return false;

        return in_array($role->slug, self::MANAGE_ROLES);
    }
}

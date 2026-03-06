<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class RestaurantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $restaurantId = request()->get('restaurant_id');

        if (!$restaurantId) {
            return;
        }

        $builder->where('restaurant_id', $restaurantId);
    }
}
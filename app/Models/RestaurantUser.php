<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RestaurantUser extends Pivot
{
    protected $table = 'restaurant_user';

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'role_id',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
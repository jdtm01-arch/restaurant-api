<?php

namespace App\Models;

use App\Models\Scopes\RestaurantScope;
use App\Models\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    use SoftDeletes, BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'number',
        'name',
        'is_active',
        'position_x',
        'position_y',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'position_x' => 'integer',
        'position_y' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new RestaurantScope);
    }

    protected function getRestoreRouteName(): string
    {
        return 'tables.restore';
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}

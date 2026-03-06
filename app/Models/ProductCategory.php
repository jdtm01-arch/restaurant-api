<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\RestaurantScope;
use App\Models\Traits\BelongsToRestaurant;
class ProductCategory extends Model
{
    use SoftDeletes, BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'name',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new RestaurantScope);
    }

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

}
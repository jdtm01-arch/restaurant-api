<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\RestaurantScope;
use App\Models\Traits\BelongsToRestaurant;

class Product extends Model
{
    use SoftDeletes, BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'description',
        'price_with_tax',
        'image_path',
        'is_active',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) return null;
        return url('storage/' . $this->image_path);
    }

    protected $casts = [
        'price_with_tax' => 'decimal:2',
        'is_active' => 'boolean',
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

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }


}
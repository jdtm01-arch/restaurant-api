<?php

namespace App\Models;

use App\Models\Scopes\RestaurantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteLog extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new RestaurantScope);
    }

    const REASON_EXPIRED = 'expired';
    const REASON_DAMAGED = 'damaged';
    const REASON_PREPARATION_ERROR = 'preparation_error';
    const REASON_RETURNED = 'returned';
    const REASON_OTHER = 'other';

    const VALID_REASONS = [
        self::REASON_EXPIRED,
        self::REASON_DAMAGED,
        self::REASON_PREPARATION_ERROR,
        self::REASON_RETURNED,
        self::REASON_OTHER,
    ];

    protected $fillable = [
        'restaurant_id',
        'product_id',
        'user_id',
        'description',
        'quantity',
        'unit',
        'estimated_cost',
        'waste_date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity'       => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'waste_date'     => 'date',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

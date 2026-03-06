<?php

namespace App\Models;

use App\Models\Scopes\RestaurantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashClosing extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new RestaurantScope);
    }

    protected $fillable = [
        'restaurant_id',
        'closed_by',
        'date',
        'total_sales',
        'total_expenses',
        'net_total',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'date'           => 'date',
            'total_sales'    => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'net_total'      => 'decimal:2',
            'closed_at'      => 'datetime',
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

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
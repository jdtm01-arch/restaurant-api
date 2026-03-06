<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Scopes\RestaurantScope;
use App\Models\Traits\BelongsToRestaurant;

class FinancialAccount extends Model
{
    use SoftDeletes, BelongsToRestaurant;

    const TYPE_CASH    = 'cash';
    const TYPE_BANK    = 'bank';
    const TYPE_DIGITAL = 'digital';
    const TYPE_POS     = 'pos';

    const TYPES = [
        self::TYPE_CASH,
        self::TYPE_BANK,
        self::TYPE_DIGITAL,
        self::TYPE_POS,
    ];

    protected $fillable = [
        'restaurant_id',
        'name',
        'type',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /* ── Scopes ── */

    protected static function booted(): void
    {
        static::addGlobalScope(new RestaurantScope());
    }

    /* ── Relations ── */

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FinancialMovement::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(AccountTransfer::class, 'from_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(AccountTransfer::class, 'to_account_id');
    }

    /* ── Helpers ── */

    public function isCash(): bool
    {
        return $this->type === self::TYPE_CASH;
    }
}

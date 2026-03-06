<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialMovement extends Model
{
    const TYPE_INCOME          = 'income';
    const TYPE_EXPENSE          = 'expense';
    const TYPE_TRANSFER_IN      = 'transfer_in';
    const TYPE_TRANSFER_OUT     = 'transfer_out';
    const TYPE_INITIAL_BALANCE  = 'initial_balance';

    const TYPES = [
        self::TYPE_INCOME,
        self::TYPE_EXPENSE,
        self::TYPE_TRANSFER_IN,
        self::TYPE_TRANSFER_OUT,
        self::TYPE_INITIAL_BALANCE,
    ];

    const REF_SALE_PAYMENT      = 'sale_payment';
    const REF_EXPENSE_PAYMENT   = 'expense_payment';
    const REF_TRANSFER          = 'transfer';
    const REF_MANUAL_ADJUSTMENT = 'manual_adjustment';
    const REF_INITIAL_BALANCE   = 'initial_balance';

    protected $fillable = [
        'restaurant_id',
        'financial_account_id',
        'type',
        'reference_type',
        'reference_id',
        'amount',
        'description',
        'movement_date',
        'created_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'movement_date' => 'date',
    ];

    /* ── Relations ── */

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

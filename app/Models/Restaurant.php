<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Restaurant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'ruc',
        'address',
        'phone',
        'active',
        'financial_initialized_at',
    ];

    protected $casts = [
        'financial_initialized_at' => 'datetime',
    ];

    public function isFinancialInitialized(): bool
    {
        return $this->financial_initialized_at !== null;
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
                    ->withPivot('role_id')
                    ->withTimestamps();
    }

    public function financialAccounts()
    {
        return $this->hasMany(FinancialAccount::class);
    }

    public function financialMovements()
    {
        return $this->hasMany(FinancialMovement::class);
    }

    public function accountTransfers()
    {
        return $this->hasMany(AccountTransfer::class);
    }
}
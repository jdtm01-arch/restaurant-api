<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'active',
    ];

    public function payments()
    {
        return $this->hasMany(ExpensePayment::class);
    }

    public function salePayments()
    {
        return $this->hasMany(SalePayment::class);
    }
}

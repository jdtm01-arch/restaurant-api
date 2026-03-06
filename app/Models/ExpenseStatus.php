<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseStatus extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'active',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
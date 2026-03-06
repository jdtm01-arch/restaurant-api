<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'expense_id',
        'changed_by',
        'field_changed',
        'old_value',
        'new_value',
        'created_at',
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'supplier_id',
        'expense_category_id',
        'expense_status_id',
        'user_id',
        'amount',
        'description',
        'expense_date',
        'paid_at',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /* ================= RELATIONS ================= */

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function status()
    {
        return $this->belongsTo(ExpenseStatus::class, 'expense_status_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(ExpensePayment::class);
    }

    public function attachments()
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function audits()
    {
        return $this->hasMany(ExpenseAudit::class);
    }
}

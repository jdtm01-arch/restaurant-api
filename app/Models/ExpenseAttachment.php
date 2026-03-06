<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseAttachment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'expense_id',
        'file_path',
        'file_name',
        'uploaded_by',
        'created_at',
    ];

    protected $appends = ['file_url'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }
        return asset('storage/' . $this->file_path);
    }
}
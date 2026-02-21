<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'balance',
        'currency',
        'color',
        'icon',
        'is_active',
        'is_debt',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_debt' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function getNetBalanceAttribute(): float
    {
        return $this->is_debt ? 0 : $this->balance;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

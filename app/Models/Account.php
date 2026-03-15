<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'balance',
        'opening_balance',
        'currency',
        'color',
        'icon',
        'is_active',
        'is_debt',
    ];

    protected $casts = [
        'opening_balance' => 'float', // ✅ float supporta negativi
        'balance'         => 'float',
        'is_active'       => 'boolean',
        'is_debt'         => 'boolean',
    ];

    // ✅ ACCESSOR: calcolato da transazioni
    public function getBalanceAttribute(): float
    {
        return (float) $this->opening_balance + $this->transactions()->sum('amount');
    }


    // ✅ ACCESSOR: negativo se debito
    public function getSignedBalanceAttribute(): float
    {
        $balance = $this->getBalanceAttribute();
        return $this->is_debt ? -abs($balance) : $balance;
    }

    // ✅ RELAZIONE
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ✅ SCOPE
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

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
    ];

    protected $casts = [
        'opening_balance' => 'float',
        'balance'         => 'float',
        'is_active'       => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($account) {
            if (auth()->check()) {
                $account->user_id = auth()->id();
            }
        });
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->opening_balance + $this->transactions()->sum('amount');
    }

    public function getSignedBalanceAttribute(): float
    {
        $balance = $this->getBalanceAttribute();
        return $balance;
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

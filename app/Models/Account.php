<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Account extends Model
{
    use HasFactory, SoftDeletes;

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
            if (Auth::user() !== null) {
                $account->user_id = Auth::user()?->id;
            }
        });
    }

    public function getSignedBalanceAttribute(): float
    {
        return (float) $this->balance;
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

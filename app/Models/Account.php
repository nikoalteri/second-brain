<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getNetBalanceAttribute(): float
    {
        $balance = (float) ($this->balance ?? 0);

        return $this->is_debt ? 0.0 : $balance;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getSignedBalanceAttribute(): float
    {
        $balance = (float) ($this->balance ?? 0);

        return $this->is_debt ? -$balance : $balance;
    }

    public function getLiquidityAttribute(): float
    {
        $balance = (float) ($this->balance ?? 0);

        return $this->is_debt ? 0.0 : $balance;
    }
}

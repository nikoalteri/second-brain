<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Traits\HasUserScoping;

class Account extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'balance',
        'opening_balance',
        'currency',
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
            $account->user_id ??= Auth::id();
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

    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class);
    }

    // ✅ SCOPE
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: filter to records belonging to the authenticated user.
     * Used by Lighthouse @scope(name: "belongsToAuthUser") on GraphQL paginated queries.
     */
    public function scopeBelongsToAuthUser($query): \Illuminate\Database\Eloquent\Builder
    {
        if (auth()->user()?->hasRole('superadmin')) {
            return $query;
        }

        return $query->where('user_id', auth()->id());
    }
}

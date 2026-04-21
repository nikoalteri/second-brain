<?php

namespace App\Models;

use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Enums\InterestCalculationMethod;
use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditCard extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $appends = [
        'available_credit',
        'is_unlimited',
    ];

    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'type',
        'credit_limit',
        'fixed_payment',
        'interest_rate',
        'stamp_duty_amount',
        'statement_day',
        'due_day',
        'skip_weekends',
        'current_balance',
        'status',
        'start_date',
        'interest_calculation_method',
    ];

    protected $casts = [
        'type' => CreditCardType::class,
        'credit_limit' => 'decimal:2',
        'fixed_payment' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'stamp_duty_amount' => 'decimal:2',
        'skip_weekends' => 'boolean',
        'current_balance' => 'decimal:2',
        'status' => CreditCardStatus::class,
        'start_date' => 'date',
        'interest_calculation_method' => InterestCalculationMethod::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(CreditCardCycle::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditCardPayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(CreditCardExpense::class);
    }

    public function getIsUnlimitedAttribute(): bool
    {
        return $this->credit_limit === null;
    }

    public function getAvailableCreditAttribute(): ?float
    {
        if ($this->credit_limit === null) {
            return null;
        }

        return round(max(0.0, (float) $this->credit_limit - (float) $this->current_balance), 2);
    }

    /**
     * Scope: filter to records belonging to the authenticated user.
     * Used by Lighthouse @scope(name: "belongsToAuthUser") on GraphQL paginated queries.
     */
    public function scopeBelongsToAuthUser($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('user_id', auth()->id());
    }
}

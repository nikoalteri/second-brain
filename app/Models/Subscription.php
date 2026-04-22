<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'name',
        'monthly_cost',
        'annual_cost',
        'subscription_frequency_id',
        'day_of_month',
        'next_renewal_date',
        'account_id',
        'credit_card_id',
        'category_id',
        'auto_create_transaction',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => SubscriptionStatus::class,
        'monthly_cost' => 'decimal:2',
        'annual_cost' => 'decimal:2',
        'next_renewal_date' => 'date',
        'auto_create_transaction' => 'boolean',
        'day_of_month' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function frequencyOption(): BelongsTo
    {
        return $this->belongsTo(SubscriptionFrequency::class, 'subscription_frequency_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function creditCardExpenses(): HasMany
    {
        return $this->hasMany(CreditCardExpense::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', SubscriptionStatus::ACTIVE);
    }

    public function scopeForRenewal($query, int $days = 7)
    {
        return $query->whereBetween('next_renewal_date', [now(), now()->addDays($days)]);
    }

    public function getFrequencyAttribute(): ?string
    {
        return $this->frequencyOption?->slug;
    }

    public function getFrequencyLabelAttribute(): ?string
    {
        return $this->frequencyOption?->name;
    }

    public function getBillingAmountAttribute(): float
    {
        $interval = max(1, (int) ($this->frequencyOption?->months_interval ?? 1));

        if ($interval === 1) {
            return round((float) ($this->monthly_cost ?? 0), 2);
        }

        return round((float) ($this->annual_cost ?? 0), 2);
    }

    public function getPaymentSourceTypeAttribute(): ?string
    {
        if ($this->credit_card_id) {
            return 'credit-card';
        }

        if ($this->account_id) {
            return 'account';
        }

        return null;
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

<?php

namespace App\Models;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'name',
        'monthly_cost',
        'annual_cost',
        'frequency',
        'day_of_month',
        'next_renewal_date',
        'account_id',
        'category_id',
        'auto_create_transaction',
        'status',
        'notes',
    ];

    protected $casts = [
        'frequency' => SubscriptionFrequency::class,
        'status' => SubscriptionStatus::class,
        'monthly_cost' => 'decimal:2',
        'annual_cost' => 'decimal:2',
        'next_renewal_date' => 'date',
        'auto_create_transaction' => 'boolean',
        'day_of_month' => 'integer',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function category()
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', SubscriptionStatus::ACTIVE);
    }

    public function scopeForRenewal($query, int $days = 7)
    {
        return $query->whereBetween('next_renewal_date', [now(), now()->addDays($days)]);
    }
}

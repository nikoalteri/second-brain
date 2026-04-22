<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_card_id',
        'credit_card_cycle_id',
        'subscription_id',
        'spent_at',
        'posted_at',
        'subscription_renewal_date',
        'amount',
        'description',
        'notes',
    ];

    protected $casts = [
        'spent_at' => 'date',
        'posted_at' => 'date',
        'subscription_renewal_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(CreditCardCycle::class, 'credit_card_cycle_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}

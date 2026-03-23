<?php

namespace App\Models;

use App\Enums\CreditCardCycleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_card_id',
        'period_month',
        'period_start_date',
        'statement_date',
        'due_date',
        'total_spent',
        'interest_amount',
        'principal_amount',
        'stamp_duty_amount',
        'total_due',
        'paid_amount',
        'status',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'statement_date' => 'date',
        'due_date' => 'date',
        'total_spent' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'stamp_duty_amount' => 'decimal:2',
        'total_due' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'status' => CreditCardCycleStatus::class,
    ];

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditCardPayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(CreditCardExpense::class);
    }
}

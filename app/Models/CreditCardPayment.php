<?php

namespace App\Models;

use App\Enums\CreditCardPaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CreditCardPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_card_id',
        'credit_card_cycle_id',
        'transaction_id',
        'due_date',
        'actual_date',
        'installment_amount',
        'interest_amount',
        'principal_amount',
        'stamp_duty_amount',
        'total_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'actual_date' => 'date',
        'installment_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'stamp_duty_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'status' => CreditCardPaymentStatus::class,
    ];

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(CreditCardCycle::class, 'credit_card_cycle_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function postingTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'credit_card_payment_id');
    }
}

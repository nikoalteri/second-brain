<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CreditCardPayment;
use App\Models\LoanPayment;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'transaction_type_id',
        'to_account_id',
        'loan_payment_id',
        'credit_card_payment_id',
        'transaction_category_id',
        'amount',
        'date',
        'competence_month',
        'description',
        'notes',
        'is_transfer',
        'transfer_pair_id',
        'transfer_direction',
    ];

    protected $casts = [
        'date'        => 'date',
        'amount'      => 'decimal:2',
        'is_transfer' => 'boolean',
    ];

    // RELAZIONI
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function type()
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    public function category()
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loanPayment(): BelongsTo
    {
        return $this->belongsTo(LoanPayment::class);
    }

    public function creditCardPayment(): BelongsTo
    {
        return $this->belongsTo(CreditCardPayment::class);
    }

    public function scopeDateFrom($query, string $date)
    {
        return $query->where('date', '>=', $date);
    }

    public function scopeDateTo($query, string $date)
    {
        return $query->where('date', '<=', $date);
    }
}

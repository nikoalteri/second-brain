<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'total_amount',
        'monthly_payment',
        'withdrawal_day',
        'skip_weekends',
        'start_date',
        'end_date',
        'total_installments',
        'paid_installments',
        'remaining_amount',
        'status',
    ];

    protected $casts = [
        'totsl_amount' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'skip_weekends' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }
}

<?php

namespace App\Models;

use App\Enums\LoanPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\LoanScheduleService;

class LoanPayment extends Model
{
    protected $fillable = [
        'loan_id',
        'due_date',
        'actual_date',
        'amount',
        'interest_rate',
        'status',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'actual_date' => 'date',
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'status' => LoanPaymentStatus::class,
    ];

    protected static function booted(): void
    {
        static::saved(function (LoanPayment $payment) {
            $loan = $payment->loan()->first();

            if ($loan) {
                app(LoanScheduleService::class)->syncLoan($loan);
            }
        });

        static::deleted(function (LoanPayment $payment) {
            $loan = $payment->loan()->first();

            if ($loan) {
                app(LoanScheduleService::class)->syncLoan($loan);
            }
        });
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}

<?php

namespace App\Observers;

use App\Models\LoanPayment;
use App\Services\LoanPaymentPostingService;

class LoanPaymentObserver
{
    public function saved(LoanPayment $payment): void
    {
        app(LoanPaymentPostingService::class)->syncPosting($payment);
    }

    public function deleted(LoanPayment $payment): void
    {
        app(LoanPaymentPostingService::class)->deletePosting($payment);
    }
}

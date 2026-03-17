<?php

namespace App\Jobs;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\LoanScheduleService;

class GenerateLoanPaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $loanId;

    public function __construct(int $loanId)
    {
        $this->loanId = $loanId;
    }

    public function handle()
    {
        $loan = Loan::find($this->loanId);
        if ($loan) {
            $service = new LoanScheduleService();
            $service->generate($loan);
        }
    }
}

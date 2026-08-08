<?php

namespace App\Observers;

use App\Models\SavingGoalContribution;
use App\Services\SavingGoalProgressService;

class SavingGoalContributionObserver
{
    public function created(SavingGoalContribution $contribution): void
    {
        app(SavingGoalProgressService::class)->handleCreated($contribution);
    }

    public function updated(SavingGoalContribution $contribution): void
    {
        app(SavingGoalProgressService::class)->handleUpdated($contribution);
    }

    public function deleted(SavingGoalContribution $contribution): void
    {
        app(SavingGoalProgressService::class)->handleDeleted($contribution);
    }
}

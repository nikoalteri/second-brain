<?php

namespace App\Services;

use App\Enums\SavingGoalStatus;
use App\Models\SavingGoal;
use App\Models\SavingGoalContribution;

/**
 * Keeps SavingGoal.current_amount in sync with its contributions, mirroring the
 * AccountBalanceService pattern: each contribution (positive = deposit, negative =
 * withdrawal) is applied as a delta on create/update/delete, never recomputed from a full
 * table scan on the hot path.
 */
class SavingGoalProgressService
{
    public function handleCreated(SavingGoalContribution $contribution): void
    {
        $goal = $contribution->savingGoal;
        $goal->increment('current_amount', $contribution->amount);
        $this->syncStatus($goal->fresh());
    }

    public function handleUpdated(SavingGoalContribution $contribution): void
    {
        $oldGoalId = (int) $contribution->getOriginal('saving_goal_id');
        $newGoalId = (int) $contribution->saving_goal_id;
        $oldAmount = (float) $contribution->getOriginal('amount');
        $newAmount = (float) $contribution->amount;

        if ($oldGoalId !== $newGoalId) {
            $oldGoal = SavingGoal::find($oldGoalId);
            $oldGoal?->decrement('current_amount', $oldAmount);
            $oldGoal && $this->syncStatus($oldGoal->fresh());

            $newGoal = $contribution->savingGoal;
            $newGoal->increment('current_amount', $newAmount);
            $this->syncStatus($newGoal->fresh());

            return;
        }

        $goal = $contribution->savingGoal;
        $goal->increment('current_amount', $newAmount - $oldAmount);
        $this->syncStatus($goal->fresh());
    }

    public function handleDeleted(SavingGoalContribution $contribution): void
    {
        $goal = $contribution->savingGoal;

        if (! $goal) {
            return;
        }

        $goal->decrement('current_amount', $contribution->amount);
        $this->syncStatus($goal->fresh());
    }

    /**
     * Flip active<->achieved automatically as progress crosses the target. Archived goals are
     * left alone — archiving is a deliberate user action, not something a contribution should
     * silently undo.
     */
    private function syncStatus(SavingGoal $goal): void
    {
        if ($goal->status === SavingGoalStatus::ARCHIVED) {
            return;
        }

        $achieved = (float) $goal->target_amount > 0
            && (float) $goal->current_amount >= (float) $goal->target_amount;

        $desired = $achieved ? SavingGoalStatus::ACHIEVED : SavingGoalStatus::ACTIVE;

        if ($goal->status !== $desired) {
            $goal->update(['status' => $desired]);
        }
    }
}

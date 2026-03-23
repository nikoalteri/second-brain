<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreditCardExpenseService
{
    public function __construct(
        private readonly CreditCardCycleService $cycleService,
        private readonly CreditCardBalanceService $balanceService
    ) {}

    public function validateExpenseChange(
        CreditCardExpense $expense,
        ?int $originalCardId = null,
        ?float $originalAmount = null
    ): void {
        DB::transaction(function () use ($expense, $originalCardId, $originalAmount) {
            $currentCard = CreditCard::query()->lockForUpdate()->find((int) $expense->credit_card_id);

            if (! $currentCard) {
                return;
            }

            $newAmount = (float) $expense->amount;

            if ($originalCardId && $originalCardId !== (int) $currentCard->id) {
                // Moving to a different card: validate new card can accept this amount
                try {
                    $this->balanceService->addExpense($currentCard, $newAmount);
                    // Revert the test addition
                    $this->balanceService->removeExpense($currentCard, $newAmount);
                } catch (\Exception $e) {
                    throw $e;
                }
                return;
            }

            // Same card: calculate delta and validate
            $oldAmount = $originalAmount ?? 0.0;
            $delta = $originalAmount === null ? $newAmount : ($newAmount - $oldAmount);

            if ($delta > 0) {
                try {
                    $this->balanceService->addExpense($currentCard, $delta);
                    // Revert the test addition
                    $this->balanceService->removeExpense($currentCard, $delta);
                } catch (\Exception $e) {
                    throw $e;
                }
            }
        });
    }

    public function syncExpense(
        CreditCardExpense $expense,
        ?int $originalCardId = null,
        ?int $originalCycleId = null,
        ?float $originalAmount = null
    ): void {
        DB::transaction(function () use ($expense, $originalCardId, $originalCycleId, $originalAmount) {
            $currentCard = CreditCard::query()->lockForUpdate()->find($expense->credit_card_id);

            if (! $currentCard) {
                return;
            }

            $oldAmount = $originalAmount ?? 0.0;
            $newAmount = (float) $expense->amount;

            if ($originalCardId && $originalCardId !== (int) $currentCard->id) {
                // Moved to different card
                $oldCard = CreditCard::query()->lockForUpdate()->find($originalCardId);
                if ($oldCard) {
                    $this->balanceService->removeExpense($oldCard, $oldAmount);
                }
                $this->balanceService->addExpense($currentCard, $newAmount);
            } else {
                // Same card: apply delta
                $delta = $originalAmount === null ? $newAmount : ($newAmount - $oldAmount);
                if ($delta > 0) {
                    $this->balanceService->addExpense($currentCard, $delta);
                } elseif ($delta < 0) {
                    $this->balanceService->removeExpense($currentCard, abs($delta));
                }
            }

            // Update cycle assignment
            $cycle = $this->resolveCycle($currentCard, $expense->spent_at ?? now());

            if ((int) $expense->credit_card_cycle_id !== (int) $cycle->id) {
                CreditCardExpense::withoutEvents(function () use ($expense, $cycle) {
                    $expense->updateQuietly(['credit_card_cycle_id' => $cycle->id]);
                });
            }

            $this->recomputeCycleTotal($cycle);

            if ($originalCycleId && $originalCycleId !== $cycle->id) {
                $oldCycle = CreditCardCycle::query()->find($originalCycleId);
                if ($oldCycle) {
                    $this->recomputeCycleTotal($oldCycle);
                }
            }

            if ($originalCardId && $originalCardId !== (int) $expense->credit_card_id) {
                $this->cycleService->syncCardById($originalCardId);
            }

            $this->cycleService->syncCardById((int) $currentCard->id);
        });
    }

    public function removeExpense(CreditCardExpense $expense): void
    {
        DB::transaction(function () use ($expense) {
            if ($expense->credit_card_id) {
                $card = CreditCard::query()->lockForUpdate()->find((int) $expense->credit_card_id);

                if ($card) {
                    $this->balanceService->removeExpense($card, (float) $expense->amount);
                }
            }

            if ($expense->credit_card_cycle_id) {
                $cycle = CreditCardCycle::query()->find($expense->credit_card_cycle_id);
                if ($cycle) {
                    $this->recomputeCycleTotal($cycle);
                }
            }

             if ($expense->credit_card_id) {
                 $this->cycleService->syncCardById((int) $expense->credit_card_id);
             }
         });
     }

    private function resolveCycle(CreditCard $card, Carbon|string $spentAt): CreditCardCycle
    {
        $date = $spentAt instanceof Carbon ? $spentAt->copy() : Carbon::parse($spentAt);

        $matchingCycle = CreditCardCycle::query()
            ->where('credit_card_id', $card->id)
            ->whereNotNull('period_start_date')
            ->whereDate('period_start_date', '<=', $date->toDateString())
            ->whereDate('statement_date', '>=', $date->toDateString())
            ->orderBy('statement_date')
            ->first();

        if ($matchingCycle) {
            return $matchingCycle;
        }

        return $this->cycleService->ensureCurrentMonthCycle($card, $date);
    }

    private function recomputeCycleTotal(CreditCardCycle $cycle): void
    {
        $total = (float) $cycle->expenses()->sum('amount');
        $cycle->update(['total_spent' => round(max(0.0, $total), 2)]);
    }
}

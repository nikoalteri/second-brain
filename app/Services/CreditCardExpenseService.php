<?php

namespace App\Services;

use App\Enums\CreditCardCycleStatus;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditCardExpenseService
{
    public function __construct(
        private readonly CreditCardCycleService $cycleService,
        private readonly CreditCardBalanceService $balanceService
    ) {}

    public function validateExpenseChange(
        CreditCardExpense $expense,
        ?int $originalCardId = null,
        ?int $originalCycleId = null,
        ?float $originalAmount = null
    ): void {
        DB::transaction(function () use ($expense, $originalCardId, $originalCycleId, $originalAmount) {
            if ($originalCycleId) {
                $originalCycle = CreditCardCycle::query()->find($originalCycleId);
                $this->ensureCycleIsMutable($originalCycle);
            }

            $lockedCards = $this->lockCards((int) $expense->credit_card_id, $originalCardId);
            $currentCard = $lockedCards[(int) $expense->credit_card_id] ?? null;

            if (! $currentCard) {
                throw ValidationException::withMessages([
                    'credit_card_id' => 'The selected credit card does not exist.',
                ]);
            }

            $targetCycle = $this->findMatchingCycle($currentCard, $expense->spent_at ?? now());
            $this->ensureCycleIsMutable($targetCycle);

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

    public function validateExpenseRemoval(CreditCardExpense $expense): void
    {
        if (! $expense->credit_card_cycle_id) {
            return;
        }

        $cycle = CreditCardCycle::query()->find((int) $expense->credit_card_cycle_id);
        $this->ensureCycleIsMutable($cycle);
    }

    public function syncExpense(
        CreditCardExpense $expense,
        ?int $originalCardId = null,
        ?int $originalCycleId = null,
        ?float $originalAmount = null
    ): void {
        DB::transaction(function () use ($expense, $originalCardId, $originalCycleId, $originalAmount) {
            $lockedCards = $this->lockCards((int) $expense->credit_card_id, $originalCardId);
            $currentCard = $lockedCards[(int) $expense->credit_card_id] ?? null;

            if (! $currentCard) {
                return;
            }

            $oldAmount = $originalAmount ?? 0.0;
            $newAmount = (float) $expense->amount;

            if ($originalCardId && $originalCardId !== (int) $currentCard->id) {
                // Moved to different card
                $oldCard = $lockedCards[$originalCardId] ?? null;
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
        });
    }

    /**
     * Locks every card an expense change involves, always in ascending id order. Moving an
     * expense between two cards touches both; if two moves in opposite directions each locked
     * their target first, they would wait for each other forever.
     *
     * @return array<int, CreditCard|null> keyed by card id
     */
    private function lockCards(?int ...$cardIds): array
    {
        $ids = collect($cardIds)->filter()->unique()->sort()->values();

        return $ids->mapWithKeys(fn (int $id) => [$id => CreditCard::query()->lockForUpdate()->find($id)])->all();
    }

    private function resolveCycle(CreditCard $card, Carbon|string $spentAt): CreditCardCycle
    {
        $date = $spentAt instanceof Carbon ? $spentAt->copy() : Carbon::parse($spentAt);

        $matchingCycle = $this->findMatchingCycle($card, $date, openOnly: true);

        if ($matchingCycle) {
            return $matchingCycle;
        }

        return $this->cycleService->ensureCurrentMonthCycle($card, $date);
    }

    private function findMatchingCycle(
        CreditCard $card,
        Carbon|string $spentAt,
        bool $openOnly = false
    ): ?CreditCardCycle {
        $date = $spentAt instanceof Carbon ? $spentAt->copy() : Carbon::parse($spentAt);

        return CreditCardCycle::query()
            ->where('credit_card_id', $card->id)
            ->whereNotNull('period_start_date')
            ->whereDate('period_start_date', '<=', $date->toDateString())
            ->whereDate('statement_date', '>=', $date->toDateString())
            ->when($openOnly, fn ($query) => $query->where('status', CreditCardCycleStatus::OPEN))
            ->orderBy('statement_date')
            ->first();
    }

    private function ensureCycleIsMutable(?CreditCardCycle $cycle): void
    {
        if (! $cycle || $cycle->status === CreditCardCycleStatus::OPEN) {
            return;
        }

        throw ValidationException::withMessages([
            'spent_at' => 'This billing cycle has already been issued. Create or use the next open cycle before changing expenses.',
        ]);
    }

    private function recomputeCycleTotal(CreditCardCycle $cycle): void
    {
        $total = (float) $cycle->expenses()->sum('amount');
        $cycle->update(['total_spent' => round(max(0.0, $total), 2)]);
        $this->cycleService->syncIssuedCycle($cycle);
    }
}

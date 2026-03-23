<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditCardExpenseService
{
    public function __construct(private readonly CreditCardCycleService $cycleService) {}

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
                $this->assertLimitNotExceeded($currentCard, $newAmount);
                return;
            }

            $oldAmount = $originalAmount ?? 0.0;
            $delta = $originalAmount === null ? $newAmount : ($newAmount - $oldAmount);

            if ($delta > 0) {
                $this->assertLimitNotExceeded($currentCard, $delta);
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
                $oldCard = CreditCard::query()->lockForUpdate()->find($originalCardId);

                if ($oldCard) {
                    $this->applyBalanceDelta($oldCard, -$oldAmount);
                }

                $this->applyBalanceDelta($currentCard, $newAmount);
            } else {
                $delta = $originalAmount === null ? $newAmount : ($newAmount - $oldAmount);
                $this->applyBalanceDelta($currentCard, $delta);
            }

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
                    $this->applyBalanceDelta($card, - ((float) $expense->amount));
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

    private function applyBalanceDelta(CreditCard $card, float $delta): void
    {
        if ($delta === 0.0) {
            return;
        }

        if ($delta > 0) {
            $this->assertLimitNotExceeded($card, $delta);
        }

        $newBalance = round(max(0.0, (float) $card->current_balance + $delta), 2);

        $card->update(['current_balance' => $newBalance]);
    }

    private function assertLimitNotExceeded(CreditCard $card, float $increase): void
    {
        if ($card->credit_limit === null) {
            return;
        }

        $candidate = round((float) $card->current_balance + $increase, 2);

        if ($candidate > (float) $card->credit_limit) {
            throw ValidationException::withMessages([
                'amount' => 'Credit limit exceeded for this card.',
            ]);
        }
    }
}

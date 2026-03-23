<?php

namespace App\Services;

use App\Enums\CreditCardCycleStatus;
use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use Carbon\Carbon;

class CreditCardKpiService
{
    public function getForUser(int $userId, ?Carbon $referenceDate = null): array
    {
        $referenceDate ??= now();
        $startOfMonth = $referenceDate->copy()->startOfMonth();
        $endOfMonth = $referenceDate->copy()->endOfMonth();

        $cardIds = CreditCard::query()
            ->where('user_id', $userId)
            ->where('status', CreditCardStatus::ACTIVE)
            ->pluck('id');

        if ($cardIds->isEmpty()) {
            return [
                'spent_this_month' => 0.0,
                'next_due_amount' => 0.0,
                'next_due_date' => null,
                'revolving_residual' => 0.0,
                'overdue_cycles_count' => 0,
                'overdue_total_due' => 0.0,
                'total_available_limited' => 0.0,
                'unlimited_cards_count' => 0,
            ];
        }

        $cards = CreditCard::query()
            ->whereIn('id', $cardIds)
            ->get(['id', 'type', 'credit_limit', 'current_balance']);

        $spentThisMonth = (float) CreditCardExpense::query()
            ->whereIn('credit_card_id', $cardIds)
            ->whereBetween('spent_at', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $nextPayment = CreditCardPayment::query()
            ->whereIn('credit_card_id', $cardIds)
            ->where('status', CreditCardPaymentStatus::PENDING)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->first();

        $revolvingResidual = (float) $cards
            ->filter(fn(CreditCard $card) => $card->type === CreditCardType::REVOLVING)
            ->sum('current_balance');

        $totalAvailableLimited = (float) $cards
            ->whereNotNull('credit_limit')
            ->sum(fn(CreditCard $card) => max(0.0, (float) $card->credit_limit - (float) $card->current_balance));

        $unlimitedCardsCount = $cards
            ->whereNull('credit_limit')
            ->count();

        $overdueCycles = CreditCardCycle::query()
            ->whereIn('credit_card_id', $cardIds)
            ->where('status', CreditCardCycleStatus::OVERDUE)
            ->get();

        $overdueTotal = (float) $overdueCycles
            ->sum(fn(CreditCardCycle $cycle) => max(0.0, (float) $cycle->total_due - (float) $cycle->paid_amount));

        return [
            'spent_this_month' => round($spentThisMonth, 2),
            'next_due_amount' => round((float) ($nextPayment?->total_amount ?? 0), 2),
            'next_due_date' => $nextPayment?->due_date,
            'revolving_residual' => round($revolvingResidual, 2),
            'overdue_cycles_count' => $overdueCycles->count(),
            'overdue_total_due' => round($overdueTotal, 2),
            'total_available_limited' => round($totalAvailableLimited, 2),
            'unlimited_cards_count' => $unlimitedCardsCount,
        ];
    }
}

<?php

namespace App\Services;

use App\Enums\SavingGoalStatus;
use App\Models\Account;
use App\Models\SavingGoal;
use App\Models\User;
use App\Services\Forecast\ForecastObligation;
use App\Services\Forecast\ForecastObligationCollector;
use Carbon\CarbonInterface;

/**
 * Known-commitments estimate for the next 30 days, per currency. It is not a guarantee of
 * spendability: unknown future income/spending is outside it, and bank balances are not
 * reconciled against an import (Phase 25 does not exist yet), so the result is never "complete".
 */
class CashFlowForecastService
{
    public const FLAG_OVERDUE = 'overdue_obligations';
    public const FLAG_POSTING_SOFT_DELETED = 'posting_soft_deleted';
    public const FLAG_PAID_WITHOUT_POSTING = 'paid_without_posting';
    public const FLAG_CARD_CHARGE_PROJECTED = 'card_charge_projected';
    public const FLAG_SOURCE_UNAVAILABLE = 'source_account_unavailable';
    public const FLAG_UNKNOWN_CURRENCY = 'unknown_currency';
    /** Liabilities, not spendable money: their balance never joins the available pool. */
    private const NON_SPENDABLE_TYPES = ['debt', 'credit_card'];

    public const FLAG_UNRECONCILED = 'bank_data_not_reconciled';

    public function __construct(private readonly ForecastObligationCollector $collector) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, ?CarbonInterface $now = null): array
    {
        $today = ($now ?? now())->copy()->startOfDay();
        $through = $today->copy()->addDays(ForecastObligationCollector::HORIZON_DAYS);

        $accounts = Account::query()->withoutUserScope()->where('user_id', $user->id)->get();

        $goalAccountIds = SavingGoal::query()->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('status', SavingGoalStatus::ACTIVE)
            ->whereNotNull('account_id')
            ->pluck('account_id')
            ->all();

        // A goal-linked account is excluded whole (D-03): the data model has no partial earmark.
        $excludedIds = [];
        $nonSpendableCount = 0;
        $inactiveIds = [];
        $unknownCurrencyAccounts = 0;
        $buckets = [];

        foreach ($accounts as $account) {
            $currency = strtoupper(trim((string) $account->currency));
            $validCurrency = (bool) preg_match('/^[A-Z]{3}$/', $currency);

            if (! $account->is_active) {
                $inactiveIds[$account->id] = true;
                continue;
            }

            if (in_array($account->type, self::NON_SPENDABLE_TYPES, true)) {
                $excludedIds[$account->id] = true;
                $nonSpendableCount++;
                continue;
            }

            $isGoal = in_array($account->id, $goalAccountIds, true);

            if ($isGoal) {
                $excludedIds[$account->id] = true;
            }

            if (! $validCurrency) {
                $unknownCurrencyAccounts++;
                continue;
            }

            $buckets[$currency] ??= $this->emptyBucket($currency);

            if ($isGoal) {
                $buckets[$currency]['excluded_account_count']++;
            } else {
                $buckets[$currency]['included_account_count']++;
                $buckets[$currency]['available_minor'] += ForecastObligation::toMinor($account->balance);
            }
        }

        $unresolved = [];
        $notDeducted = $excludedIds + $inactiveIds;

        foreach ($this->collector->collect($user, $today) as $obligation) {
            if ($obligation->accountId !== null
                && isset($notDeducted[$obligation->accountId])
                && ($obligation->cashOutflowMinor > 0 || $obligation->isCoverageGap())) {
                $obligation = $obligation->withExclusion(ForecastObligation::REASON_ACCOUNT_EXCLUDED);
            }

            if ($obligation->currency === null) {
                $unresolved[] = $obligation;
                continue;
            }

            $buckets[$obligation->currency] ??= $this->emptyBucket($obligation->currency);
            $buckets[$obligation->currency]['obligations'][] = $obligation;
        }

        ksort($buckets);

        $flagCounts = [];
        $bump = function (string $flag, int $by = 1) use (&$flagCounts): void {
            $flagCounts[$flag] = ($flagCounts[$flag] ?? 0) + $by;
        };

        $currencies = [];
        foreach ($buckets as $bucket) {
            $counted = 0;
            $window = [];
            $overdue = [];
            $bucketFlags = [self::FLAG_UNRECONCILED => true];

            foreach ($bucket['obligations'] as $obligation) {
                $counted += $obligation->cashOutflowMinor;

                if ($obligation->overdue) {
                    $overdue[] = $obligation->toArray();
                } else {
                    $window[] = $obligation->toArray();
                }

                foreach ($this->flagsFor($obligation) as $flag) {
                    $bucketFlags[$flag] = true;
                    $bump($flag);
                }
            }

            $currencies[] = [
                'currency' => $bucket['currency'],
                'available_balance' => ForecastObligation::format($bucket['available_minor']),
                'counted_commitments' => ForecastObligation::format($counted),
                // Negative when known commitments exceed the balance: never clamped to zero.
                'estimated_remaining' => ForecastObligation::format($bucket['available_minor'] - $counted),
                'included_account_count' => $bucket['included_account_count'],
                'excluded_account_count' => $bucket['excluded_account_count'],
                'obligations' => $window,
                'overdue_obligations' => $overdue,
                'coverage_flags' => array_keys($bucketFlags),
                'complete' => false,
            ];
        }

        foreach ($unresolved as $obligation) {
            foreach ($this->flagsFor($obligation) as $flag) {
                $bump($flag);
            }
        }

        if ($unknownCurrencyAccounts > 0) {
            $bump(self::FLAG_UNKNOWN_CURRENCY, $unknownCurrencyAccounts);
        }

        // Nothing reconciles bank balances yet, so the flag is unconditional; a future import
        // phase can make it conditional without changing the response shape.
        $bump(self::FLAG_UNRECONCILED);

        $flags = [];
        foreach ($flagCounts as $code => $count) {
            $flags[] = ['code' => $code, 'count' => $count];
        }

        return [
            'as_of' => $today->toDateString(),
            'through_date' => $through->toDateString(),
            'horizon_days' => ForecastObligationCollector::HORIZON_DAYS,
            'currencies' => $currencies,
            'unresolved_obligations' => array_map(fn (ForecastObligation $o) => $o->toArray(), $unresolved),
            'coverage' => [
                'complete' => false,
                'flags' => $flags,
                'inactive_account_count' => count($inactiveIds),
                'non_spendable_account_count' => $nonSpendableCount,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function flagsFor(ForecastObligation $obligation): array
    {
        if (! $obligation->isCoverageGap()) {
            return [];
        }

        $flags = [];

        if ($obligation->overdue) {
            $flags[] = self::FLAG_OVERDUE;
        }

        $byReason = match ($obligation->reason) {
            ForecastObligation::REASON_POSTING_SOFT_DELETED => self::FLAG_POSTING_SOFT_DELETED,
            ForecastObligation::REASON_PAID_WITHOUT_POSTING => self::FLAG_PAID_WITHOUT_POSTING,
            ForecastObligation::REASON_CARD_CHARGE_PROJECTED => self::FLAG_CARD_CHARGE_PROJECTED,
            ForecastObligation::REASON_SOURCE_UNAVAILABLE => self::FLAG_SOURCE_UNAVAILABLE,
            ForecastObligation::REASON_UNKNOWN_CURRENCY => self::FLAG_UNKNOWN_CURRENCY,
            default => null,
        };

        if ($byReason !== null) {
            $flags[] = $byReason;
        }

        return $flags;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBucket(string $currency): array
    {
        return [
            'currency' => $currency,
            'available_minor' => 0,
            'included_account_count' => 0,
            'excluded_account_count' => 0,
            'obligations' => [],
        ];
    }
}

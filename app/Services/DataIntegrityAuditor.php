<?php

namespace App\Services;

use App\Enums\CreditCardPaymentStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only consistency checks over the financial data.
 *
 * Every check only SELECTs and reports row identifiers (never descriptions or notes), so the
 * output is safe to paste into a ticket. Meant to be run against real data before and after
 * fixes to the write paths; it never repairs anything.
 */
class DataIntegrityAuditor
{
    private const EPSILON = 0.005;

    /**
     * @return array<string, array{description: string, count: int, ids: list<int|string>}>
     */
    public function run(int $limit = 25): array
    {
        $checks = [
            // Rows that reference another user's account, category or card.
            'transactions_on_foreign_account' => ['Transactions on an account owned by another user',
                $this->foreign('transactions', 'account_id', 'accounts')],
            'transactions_to_foreign_account' => ['Transfer targets owned by another user',
                $this->foreign('transactions', 'to_account_id', 'accounts')],
            'transactions_with_foreign_category' => ['Transactions with a category owned by another user',
                $this->foreign('transactions', 'transaction_category_id', 'transaction_categories')],
            'loans_on_foreign_account' => ['Loans on an account owned by another user',
                $this->foreign('loans', 'account_id', 'accounts', sourceSoftDeletes: false)],
            'credit_cards_on_foreign_account' => ['Credit cards on an account owned by another user',
                $this->foreign('credit_cards', 'account_id', 'accounts')],
            'subscriptions_on_foreign_account' => ['Subscriptions on an account owned by another user',
                $this->foreign('subscriptions', 'account_id', 'accounts')],
            'subscriptions_on_foreign_card' => ['Subscriptions on a credit card owned by another user',
                $this->foreign('subscriptions', 'credit_card_id', 'credit_cards')],
            'subscriptions_with_foreign_category' => ['Subscriptions with a category owned by another user',
                $this->foreign('subscriptions', 'category_id', 'transaction_categories')],
            'saving_goals_on_foreign_account' => ['Saving goals on an account owned by another user',
                $this->foreign('saving_goals', 'account_id', 'accounts')],

            // Live rows pointing at soft-deleted accounts or cards.
            'transactions_on_deleted_account' => ['Live transactions on a deleted account',
                $this->onDeleted('transactions', 'account_id', 'accounts')],
            'loans_on_deleted_account' => ['Loans on a deleted account',
                $this->onDeleted('loans', 'account_id', 'accounts', sourceSoftDeletes: false)],
            'credit_cards_on_deleted_account' => ['Live credit cards on a deleted account',
                $this->onDeleted('credit_cards', 'account_id', 'accounts')],
            'subscriptions_on_deleted_account' => ['Live subscriptions on a deleted account',
                $this->onDeleted('subscriptions', 'account_id', 'accounts')],
            'subscriptions_on_deleted_card' => ['Live subscriptions on a deleted credit card',
                $this->onDeleted('subscriptions', 'credit_card_id', 'credit_cards')],
            'saving_goals_on_deleted_account' => ['Live saving goals on a deleted account',
                $this->onDeleted('saving_goals', 'account_id', 'accounts')],

            // Transfers are two legs sharing a transfer_pair_id.
            'transfer_legs_without_pair' => ['Transfer legs with no pair id',
                $this->transferLegsWithoutPair()],
            'transfer_pairs_with_one_leg' => ['Transfer pairs with a single live leg',
                $this->transferPairsByLegCount('=', 1)],
            'transfer_pairs_with_extra_legs' => ['Transfer pairs with more than two live legs',
                $this->transferPairsByLegCount('>', 2)],
            'transfer_pairs_unbalanced' => ['Transfer pairs whose legs do not sum to zero',
                $this->unbalancedTransferPairs()],
            'transfer_pairs_target_mismatch' => ['Transfer pairs whose OUT target is not the IN account',
                $this->transferTargetMismatch()],

            // Stored balances that no longer match the ledger.
            'account_balance_drift' => ['Accounts whose balance differs from opening balance + movements',
                $this->accountBalanceDrift()],
            'credit_card_balance_drift' => ['Cards whose balance differs from opening balance + expenses - paid principal',
                $this->creditCardBalanceDrift()],
        ];

        $report = [];

        foreach ($checks as $key => [$description, $found]) {
            $report[$key] = [
                'description' => $description,
                'count' => count($found),
                'ids' => array_slice($found, 0, $limit),
            ];
        }

        return $report;
    }

    /** Source rows whose referenced row belongs to a different user. @return list<int|string> */
    private function foreign(string $source, string $foreignKey, string $reference, bool $sourceSoftDeletes = true): array
    {
        $query = DB::table("{$source} as s")
            ->join("{$reference} as r", 'r.id', '=', "s.{$foreignKey}")
            ->whereColumn('s.user_id', '<>', 'r.user_id');

        if ($sourceSoftDeletes) {
            $query->whereNull('s.deleted_at');
        }

        return $query->orderBy('s.id')->pluck('s.id')->all();
    }

    /** Live source rows whose referenced row is soft-deleted. @return list<int|string> */
    private function onDeleted(string $source, string $foreignKey, string $reference, bool $sourceSoftDeletes = true): array
    {
        $query = DB::table("{$source} as s")
            ->join("{$reference} as r", 'r.id', '=', "s.{$foreignKey}")
            ->whereNotNull('r.deleted_at');

        if ($sourceSoftDeletes) {
            $query->whereNull('s.deleted_at');
        }

        return $query->orderBy('s.id')->pluck('s.id')->all();
    }

    /** @return list<int|string> */
    private function transferLegsWithoutPair(): array
    {
        return $this->liveTransfers()
            ->where('is_transfer', true)
            ->whereNull('transfer_pair_id')
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /** @return list<int|string> */
    private function transferPairsByLegCount(string $operator, int $legs): array
    {
        return $this->liveTransfers()
            ->whereNotNull('transfer_pair_id')
            ->groupBy('transfer_pair_id')
            ->havingRaw("COUNT(*) {$operator} ?", [$legs])
            ->orderBy('transfer_pair_id')
            ->pluck('transfer_pair_id')
            ->all();
    }

    /** @return list<int|string> */
    private function unbalancedTransferPairs(): array
    {
        return $this->liveTransfers()
            ->whereNotNull('transfer_pair_id')
            ->groupBy('transfer_pair_id')
            ->havingRaw('COUNT(*) = 2')
            // Constant inlined on purpose: a bound float is sent as text by some drivers and
            // compares as greater than any number.
            ->havingRaw('ABS(SUM(amount)) > '.self::EPSILON)
            ->orderBy('transfer_pair_id')
            ->pluck('transfer_pair_id')
            ->all();
    }

    /** @return list<int|string> */
    private function transferTargetMismatch(): array
    {
        return DB::table('transactions as o')
            ->join('transactions as i', 'i.transfer_pair_id', '=', 'o.transfer_pair_id')
            ->whereNull('o.deleted_at')
            ->whereNull('i.deleted_at')
            ->where('o.transfer_direction', 'out')
            ->where('i.transfer_direction', 'in')
            ->whereNotNull('o.to_account_id')
            ->whereColumn('o.to_account_id', '<>', 'i.account_id')
            ->orderBy('o.transfer_pair_id')
            ->pluck('o.transfer_pair_id')
            ->all();
    }

    /** @return list<string> account id and the amount the balance is off by */
    private function accountBalanceDrift(): array
    {
        $ledger = DB::table('transactions')
            ->whereNull('deleted_at')
            ->groupBy('account_id')
            ->selectRaw('account_id, SUM(amount) as total')
            ->pluck('total', 'account_id');

        $drift = [];

        foreach (DB::table('accounts')->whereNull('deleted_at')->orderBy('id')->get(['id', 'balance', 'opening_balance']) as $account) {
            $expected = (float) $account->opening_balance + (float) ($ledger[$account->id] ?? 0);
            $difference = round((float) $account->balance - $expected, 2);

            if (abs($difference) > self::EPSILON) {
                $drift[] = "{$account->id} (off by {$difference})";
            }
        }

        return $drift;
    }

    /** @return list<string> card id and the amount the balance is off by */
    private function creditCardBalanceDrift(): array
    {
        $expenses = DB::table('credit_card_expenses')
            ->groupBy('credit_card_id')
            ->selectRaw('credit_card_id, SUM(amount) as total')
            ->pluck('total', 'credit_card_id');

        $principal = DB::table('credit_card_payments')
            ->where('status', CreditCardPaymentStatus::PAID->value)
            ->groupBy('credit_card_id')
            ->selectRaw('credit_card_id, SUM(principal_amount) as total')
            ->pluck('total', 'credit_card_id');

        $drift = [];

        foreach (DB::table('credit_cards')->whereNull('deleted_at')->orderBy('id')->get(['id', 'current_balance', 'opening_balance']) as $card) {
            $expected = max(0.0, (float) $card->opening_balance + (float) ($expenses[$card->id] ?? 0) - (float) ($principal[$card->id] ?? 0));
            $difference = round((float) $card->current_balance - $expected, 2);

            if (abs($difference) > self::EPSILON) {
                $drift[] = "{$card->id} (off by {$difference})";
            }
        }

        return $drift;
    }

    private function liveTransfers(): Builder
    {
        return DB::table('transactions')->whereNull('deleted_at');
    }
}

<?php

namespace App\Support;

use App\Models\Account;
use App\Models\CreditCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one place that takes the row locks serialising writes to a ledger target.
 *
 * Rule (Phase 25-05): a write that can create, move or restore a ledger movement locks its target
 * row (accounts for Transaction, credit_cards for CreditCardExpense) BEFORE its first plain read and
 * BEFORE the insert, inside its own database transaction, and keeps the lock until commit. A reader
 * that needs "no one else can add the matching movement while I check and insert" (the statement
 * import) takes the same lock first, so its check-then-insert is atomic against every other writer.
 *
 * Order, everywhere: domain locks (import batch, subscription, payment/loan rows) first, then account
 * rows, then card rows; several accounts in ONE statement ordered by ascending id. Locking a row the
 * transaction already holds is a no-op.
 */
class LedgerLock
{
    /**
     * @param iterable<int|null> $ids
     */
    public function accounts(iterable $ids): void
    {
        $ids = collect($ids)->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values();

        if ($ids->isEmpty() || ! $this->insideTransaction('accounts')) {
            return;
        }

        // withoutGlobalScopes: the lock must not depend on who is logged in, and soft-deleted rows count.
        Account::query()->withoutGlobalScopes()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');
    }

    public function card(?int $cardId): void
    {
        if (! $cardId || ! $this->insideTransaction('credit_cards')) {
            return;
        }

        CreditCard::query()->withoutGlobalScopes()->whereKey($cardId)->lockForUpdate()->first(['id']);
    }

    /**
     * A row lock only lasts until the end of the statement's transaction: outside one it protects
     * nothing, so it is reported (once per call site) rather than silently skipped.
     */
    private function insideTransaction(string $table): bool
    {
        if (DB::transactionLevel() > 0) {
            return true;
        }

        Log::warning("LedgerLock on {$table} requested outside a database transaction; the write is not serialised.", [
            'caller' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6))
                ->map(fn ($frame) => ($frame['class'] ?? '') . '::' . ($frame['function'] ?? ''))
                ->all(),
        ]);

        return false;
    }
}

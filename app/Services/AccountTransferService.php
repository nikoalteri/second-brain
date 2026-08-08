<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountTransferService
{
    /**
     * Move money between two accounts belonging to the SAME user, as a linked pair of
     * Transaction rows (one 'out', one 'in', sharing transfer_pair_id). Two rows — rather than
     * the unused to_account_id-on-a-single-row shape — because AccountBalanceService only ever
     * adjusts the owning account_id's balance; a single row can't move money on both sides at
     * once. Both legs are tagged is_transfer=true so FinanceReportService already excludes them
     * from income/expense totals.
     *
     * Ownership is enforced here (from->user_id === to->user_id), not just at the API layer:
     * the Filament admin panel exempts superadmin from HasUserScoping, so its account picker
     * can list accounts across every user — without this check a superadmin could silently
     * move money between two different customers' books.
     *
     * @return array{out: Transaction, in: Transaction}
     */
    public function transfer(
        Account $from,
        Account $to,
        float $amount,
        string $date,
        ?string $description = null,
        ?string $notes = null,
    ): array {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'to_account_id' => 'Source and destination accounts must be different.',
            ]);
        }

        if ((int) $from->user_id !== (int) $to->user_id) {
            throw ValidationException::withMessages([
                'to_account_id' => 'Both accounts must belong to the same user.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The transfer amount must be greater than zero.',
            ]);
        }

        $ownerId = $from->user_id;

        return DB::transaction(function () use ($ownerId, $from, $to, $amount, $date, $description, $notes) {
            $transferType = TransactionType::query()->firstOrCreate(
                ['name' => 'Transfer'],
                ['is_income' => false],
            );

            $pairId = (string) Str::uuid();
            $description = $description ?: "Transfer: {$from->name} → {$to->name}";

            $out = Transaction::create([
                'user_id' => $ownerId,
                'account_id' => $from->id,
                'to_account_id' => $to->id,
                'transaction_type_id' => $transferType->id,
                'amount' => $amount,
                'date' => $date,
                'description' => $description,
                'notes' => $notes,
                'is_transfer' => true,
                'transfer_pair_id' => $pairId,
                'transfer_direction' => 'out',
            ]);

            $in = Transaction::create([
                'user_id' => $ownerId,
                'account_id' => $to->id,
                'transaction_type_id' => $transferType->id,
                'amount' => $amount,
                'date' => $date,
                'description' => $description,
                'notes' => $notes,
                'is_transfer' => true,
                'transfer_pair_id' => $pairId,
                'transfer_direction' => 'in',
            ]);

            return ['out' => $out, 'in' => $in];
        });
    }
}

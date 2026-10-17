<?php

namespace App\Services\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardExpense;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns a draft row into a real Transaction or CreditCardExpense, exactly once. The record is built
 * through the same models and observers as the manual API, so balances, cycles and ownership rules
 * are the ones already in force. Nothing here runs before an explicit confirmation by the owner.
 */
class ImportRowPromotionService
{
    /** Flags that make "confirm all" skip a row: they need the owner's decision. */
    public const REVIEW_FLAGS = [
        'already_imported', 'currency_mismatch', 'card_credit',
        'possible_transfer', 'possible_card_payment', 'matches_generated_renewal',
    ];

    /**
     * @return array{row_id: int, outcome: 'confirmed'|'skipped'|'failed', reason: ?string}
     */
    public function promote(ImportBatch $batch, int $rowId): array
    {
        try {
            return DB::transaction(function () use ($batch, $rowId) {
                // The row lock plus the status check make a repeated or concurrent confirmation a no-op.
                $row = ImportRow::query()
                    ->where('import_batch_id', $batch->id)
                    ->lockForUpdate()
                    ->find($rowId);

                if ($row === null) {
                    return $this->result($rowId, 'skipped', 'The row does not belong to this import.');
                }

                if ($row->status !== ImportRowStatus::DRAFT) {
                    return $this->result($rowId, 'skipped', 'The row is not a draft.');
                }

                if ($row->normalized === null || $row->error !== null) {
                    return $this->result($rowId, 'skipped', 'The row has no valid data; fix the mapping or discard it.');
                }

                $created = $batch->account_id
                    ? $this->postTransaction($batch, $row)
                    : $this->postCardExpense($batch, $row);

                if (is_string($created)) {
                    return $this->result($rowId, 'skipped', $created);
                }

                $row->forceFill([
                    'status' => ImportRowStatus::CONFIRMED,
                    'failure_reason' => null,
                    ...($created instanceof Transaction
                        ? ['transaction_id' => $created->id]
                        : ['credit_card_expense_id' => $created->id]),
                ])->save();

                return $this->result($rowId, 'confirmed', null);
            });
        } catch (Throwable $e) {
            $reason = $this->safeReason($e);

            // The transaction rolled back: the row stays a draft so it can be retried.
            ImportRow::query()->where('import_batch_id', $batch->id)->whereKey($rowId)
                ->where('status', ImportRowStatus::DRAFT->value)
                ->update(['failure_reason' => $reason]);

            return $this->result($rowId, 'failed', $reason);
        }
    }

    /** Marks the batch completed once no draft row is left. */
    public function completeIfDone(ImportBatch $batch): void
    {
        if (! $batch->rows()->where('status', ImportRowStatus::DRAFT->value)->exists()) {
            $batch->forceFill(['status' => ImportBatchStatus::COMPLETED])->save();
        }
    }

    /**
     * @return Transaction|string a created record, or the reason it was skipped
     */
    private function postTransaction(ImportBatch $batch, ImportRow $row): Transaction|string
    {
        $n = $row->normalized;

        $account = Account::query()->withoutUserScope()
            ->where('user_id', $batch->user_id)
            ->find($batch->account_id);

        if ($account === null || ! $account->is_active) {
            return 'The target account is no longer available.';
        }

        if ($n['amount_minor'] === 0) {
            return 'A zero amount cannot be posted.';
        }

        $income = $n['amount_minor'] > 0;
        // Same lookup the manual flows use; the model forces the sign from the type.
        $type = TransactionType::query()->firstOrCreate(
            ['name' => $income ? 'Income' : 'Expense'],
            ['is_income' => $income],
        );

        return Transaction::create([
            'user_id' => $batch->user_id,
            'account_id' => $account->id,
            'transaction_type_id' => $type->id,
            'transaction_category_id' => $n['category_id'] ?? null,
            'amount' => $n['amount_minor'] / 100,
            'date' => $n['date'],
            'description' => $n['description'] !== '' ? $n['description'] : 'Imported transaction',
            'is_transfer' => false,
        ]);
    }

    /**
     * @return CreditCardExpense|string
     */
    private function postCardExpense(ImportBatch $batch, ImportRow $row): CreditCardExpense|string
    {
        $n = $row->normalized;

        $card = CreditCard::query()->withoutUserScope()
            ->where('user_id', $batch->user_id)
            ->find($batch->credit_card_id);

        if ($card === null) {
            return 'The target credit card is no longer available.';
        }

        // Refunds and payments on a card statement change the debt differently from a charge; they are
        // never posted automatically.
        if ($n['amount_minor'] >= 0) {
            return 'Card credits and payments are not imported automatically; discard this row.';
        }

        return CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => $n['date'],
            'posted_at' => $n['date'],
            'amount' => abs($n['amount_minor']) / 100,
            'description' => $n['description'] !== '' ? $n['description'] : 'Imported expense',
        ]);
    }

    private function safeReason(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return mb_substr((string) collect($e->errors())->flatten()->first(), 0, 190);
        }

        Log::error('Import row promotion failed', ['exception' => $e]);

        return 'The row could not be posted.';
    }

    /**
     * @return array{row_id: int, outcome: string, reason: ?string}
     */
    private function result(int $rowId, string $outcome, ?string $reason): array
    {
        return ['row_id' => $rowId, 'outcome' => $outcome, 'reason' => $reason];
    }
}

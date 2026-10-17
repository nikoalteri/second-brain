<?php

namespace App\Services\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stages an uploaded statement as draft rows. It never creates a Transaction or CreditCardExpense:
 * nothing from a file affects a balance until a later, explicit confirmation.
 */
class ImportBatchService
{
    public function __construct(private readonly ImportFileReader $reader) {}

    /**
     * @param array{account_id?: ?int, credit_card_id?: ?int, statement_ending_balance?: mixed, period_start?: ?string, period_end?: ?string} $attributes
     */
    public function stage(User $owner, UploadedFile $file, array $attributes): ImportBatch
    {
        [$account, $card] = $this->resolveTarget($owner, $attributes);
        $settlement = $account ?? $card->account;
        $currency = strtoupper(trim((string) $settlement?->currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages(['account_id' => 'The target has no valid currency, so it cannot receive an import.']);
        }

        try {
            $parsed = $this->reader->read($file->getRealPath(), (string) $file->getClientOriginalExtension());
        } catch (ImportFileException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return DB::transaction(function () use ($owner, $file, $attributes, $account, $card, $currency, $parsed) {
            $batch = ImportBatch::create([
                'user_id' => $owner->id,
                'account_id' => $account?->id,
                'credit_card_id' => $card?->id,
                'currency' => $currency,
                'status' => ImportBatchStatus::UPLOADED,
                'file_format' => $parsed['format'],
                'original_filename' => mb_substr(preg_replace('/[^\p{L}\p{N} ._-]/u', '_', basename((string) $file->getClientOriginalName())), 0, 190),
                'row_count' => count($parsed['rows']),
                'statement_ending_balance' => $attributes['statement_ending_balance'] ?? null,
                'period_start' => $attributes['period_start'] ?? null,
                'period_end' => $attributes['period_end'] ?? null,
            ]);

            $now = now();

            foreach (array_chunk($parsed['rows'], 500, true) as $chunk) {
                $records = [];

                foreach ($chunk as $index => $cells) {
                    $records[] = [
                        'import_batch_id' => $batch->id,
                        'row_number' => $index + 1,
                        // Same encoding as the model's encrypted:array cast, but one bulk insert per chunk.
                        'raw_cells' => Crypt::encryptString(json_encode($cells, JSON_THROW_ON_ERROR)),
                        'status' => ImportRowStatus::DRAFT->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                ImportRow::insert($records);
            }

            return $batch;
        });
    }

    /**
     * Imports are personal even for a superadmin, so the target must belong to the uploader.
     *
     * @return array{0: ?Account, 1: ?CreditCard}
     */
    private function resolveTarget(User $owner, array $attributes): array
    {
        if (! empty($attributes['account_id'])) {
            $account = Account::query()->withoutUserScope()
                ->where('user_id', $owner->id)
                ->find($attributes['account_id']);

            if ($account === null || ! $account->is_active) {
                throw ValidationException::withMessages(['account_id' => 'The selected account is not available for import.']);
            }

            return [$account, null];
        }

        $card = CreditCard::query()->withoutUserScope()
            ->with('account')
            ->where('user_id', $owner->id)
            ->find($attributes['credit_card_id'] ?? null);

        if ($card === null) {
            throw ValidationException::withMessages(['credit_card_id' => 'The selected credit card is not available for import.']);
        }

        return [null, $card];
    }
}

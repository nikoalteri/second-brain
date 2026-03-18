<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Account;
use App\Models\TransactionType;
use Carbon\Carbon;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;
    protected function authorizeAccess(): void
    {
        $this->authorize('update', $this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['competence_month'] = \Carbon\Carbon::parse($data['date'])->format('Y-m');

        $type = \App\Models\TransactionType::find($data['transaction_type_id']);
        $isTransfer = strcasecmp((string) ($type?->name ?? ''), 'Transfer') === 0;
        $isIncome = (bool) ($type?->is_income ?? false);

        // Keep sign consistent across all type names.
        $data['amount'] = ($isTransfer || ! $isIncome)
            ? -abs((float) $data['amount'])
            : abs((float) $data['amount']);

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record->fresh(['type']);
        $isTransfer = strcasecmp((string) ($record->type?->name ?? ''), 'Transfer') === 0;

        // Se il tipo è stato cambiato da Transfer a qualcos'altro, elimina il pair
        if (! $isTransfer) {
            if ($record->transfer_pair_id) {
                \App\Models\Transaction::where('transfer_pair_id', $record->transfer_pair_id)
                    ->where('id', '!=', $record->id)
                    ->each(fn($t) => $t->delete());

                $record->updateQuietly([
                    'is_transfer'        => false,
                    'transfer_pair_id'   => null,
                    'transfer_direction' => null,
                ]);
            }
            return;
        }

        if (! $record->to_account_id) {
            return;
        }

        $newAmount  = abs((float) $record->amount);
        $newDestId  = $record->to_account_id;

        // Cerca la transazione IN paired esistente
        $pair = $record->transfer_pair_id
            ? \App\Models\Transaction::where('transfer_pair_id', $record->transfer_pair_id)
            ->where('id', '!=', $record->id)
            ->first()
            : null;

        if ($pair) {
            $oldAmount = (float) $pair->amount;
            $oldDestId = $pair->account_id;

            // Aggiorna il pair silenziosamente e gestisci il saldo manualmente
            $pair->updateQuietly([
                'account_id'        => $newDestId,
                'amount'            => $newAmount,
                'date'              => $record->date,
                'competence_month'  => $record->competence_month,
                'description'       => trim(str_replace(' (IN)', '', (string) $pair->description)) . ' (IN)',
                'notes'             => $record->notes,
            ]);

            // Correggi i saldi in base alle variazioni
            if ($oldDestId !== $newDestId) {
                // Account destinazione cambiato: ripristina il vecchio, incrementa il nuovo
                \App\Models\Account::find($oldDestId)?->decrement('balance', $oldAmount);
                \App\Models\Account::find($newDestId)?->increment('balance', $newAmount);
            } elseif ($oldAmount !== $newAmount) {
                \App\Models\Account::find($newDestId)?->increment('balance', $newAmount - $oldAmount);
            }
        } else {
            // Legacy: nessun pair trovato — crealo e collega entrambi i record
            $pairId = (string) \Illuminate\Support\Str::uuid();

            $record->updateQuietly([
                'is_transfer'        => true,
                'transfer_pair_id'   => $pairId,
                'transfer_direction' => 'out',
            ]);

            \App\Models\Transaction::create([
                'user_id'               => $record->user_id,
                'account_id'            => $newDestId,
                'transaction_type_id'   => $record->transaction_type_id,
                'transaction_category_id' => $record->transaction_category_id,
                'amount'                => $newAmount,
                'date'                  => $record->date,
                'competence_month'      => $record->competence_month,
                'description'           => trim((string) $record->description) . ' (IN)',
                'notes'                 => $record->notes,
                'is_transfer'           => true,
                'transfer_pair_id'      => $pairId,
                'transfer_direction'    => 'in',
                'to_account_id'         => null,
            ]);
        }
    }
}

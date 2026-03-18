<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\TransactionType;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;
    protected function authorizeAccess(): void
    {
        $this->authorize('create', static::$resource::getModel());
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['competence_month'] = \Carbon\Carbon::parse($data['date'])->format('Y-m');

        $type = \App\Models\TransactionType::find($data['transaction_type_id']);
        $isTransfer = strcasecmp((string) ($type?->name ?? ''), 'Transfer') === 0;
        $isIncome = (bool) ($type?->is_income ?? false);

        // Non-income types are always stored as negative amounts, except explicit incomes.
        $data['amount'] = ($isTransfer || ! $isIncome)
            ? -abs((float) $data['amount'])
            : abs((float) $data['amount']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if (strcasecmp((string) ($record->type?->name ?? ''), 'Transfer') !== 0 || ! $record->to_account_id) {
            return;
        }

        $pairId = (string) \Illuminate\Support\Str::uuid();

        // Marca il record OUT (senza triggerare l'observer sui saldi, l'importo non cambia)
        $record->updateQuietly([
            'is_transfer'        => true,
            'transfer_pair_id'   => $pairId,
            'transfer_direction' => 'out',
        ]);

        // Crea la transazione IN paired
        \App\Models\Transaction::create([
            'user_id'               => $record->user_id,
            'account_id'            => $record->to_account_id,
            'transaction_type_id'   => $record->transaction_type_id,
            'transaction_category_id' => $record->transaction_category_id,
            'amount'                => abs((float) $record->amount),
            'date'                  => $record->date,
            'competence_month'      => $record->competence_month,
            'description'           => $record->description . ' (IN)',
            'notes'                 => $record->notes,
            'is_transfer'           => true,
            'transfer_pair_id'      => $pairId,
            'transfer_direction'    => 'in',
            'to_account_id'         => null,
        ]);
    }
}

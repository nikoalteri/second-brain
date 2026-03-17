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

    // ✅ QUI sotto mutateFormDataBeforeCreate
    protected function afterCreate(): void
    {
        $record = $this->record;

        if (strcasecmp((string) ($record->type?->name ?? ''), 'Transfer') === 0 && $record->to_account_id) {
            $inData = $record->toArray();
            $inData['account_id'] = $record->to_account_id;
            $inData['amount'] = abs($record->amount);
            $inData['description'] .= ' (IN)';
            $inData['is_transfer'] = true;

            \App\Models\Transaction::create($inData);
        }
    }
}

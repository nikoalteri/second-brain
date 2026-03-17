<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
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

    // ✅ QUI sotto mutateFormDataBeforeSave
    protected function afterSave(): void
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

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
        // Validazione avanzata tramite FormRequest
        $request = app(\App\Http\Requests\StoreTransactionRequest::class);
        $request->merge($data);
        $validated = app('validator')->make(
            $request->all(),
            (new \App\Http\Requests\StoreTransactionRequest())->rules()
        )->validate();
        $data = array_merge($data, $validated);

        $data['user_id'] = auth()->id();
        $data['competence_month'] = \Carbon\Carbon::parse($data['date'])->format('Y-m');

        $type = \App\Models\TransactionType::find($data['transaction_type_id']);

        $data['amount'] = match ($type?->name) {
            'Expenses'  => -abs($data['amount']),
            'Earnings'  => abs($data['amount']),
            'Cashback'  => abs($data['amount']),
            'Transfer'  => -abs($data['amount']),
            default     => $data['amount'],
        };

        return $data;
    }

    // ✅ QUI sotto mutateFormDataBeforeCreate
    protected function afterCreate(): void
    {
        $record = $this->record;

        if ($record->type->name === 'Transfer' && $record->to_account_id) {
            $inData = $record->toArray();
            $inData['account_id'] = $record->to_account_id;
            $inData['amount'] = abs($record->amount);
            $inData['description'] .= ' (IN)';
            $inData['is_transfer'] = true;

            \App\Models\Transaction::create($inData);
        }
    }
}

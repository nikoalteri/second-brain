<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use Filament\Resources\Pages\EditRecord;

class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;

    protected function authorizeAccess(): void
    {
        $this->authorize('update', $this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validazione avanzata tramite FormRequest
        $request = app(\App\Http\Requests\StoreLoanRequest::class);
        $request->merge($data);
        $validated = app('validator')->make(
            $request->all(),
            (new \App\Http\Requests\StoreLoanRequest())->rules()
        )->validate();
        $data = array_merge($data, $validated);

        $data['user_id'] = $this->record->user_id ?? auth()->id();

        if (blank($data['paid_installments'] ?? null)) {
            $data['paid_installments'] = $this->record->paid_installments ?? 0;
        }

        if (blank($data['total_installments'] ?? null)) {
            $data['total_installments'] = $this->record->total_installments ?? 1;
        }

        if (blank($data['remaining_amount'] ?? null)) {
            $data['remaining_amount'] = $this->record->remaining_amount ?? $this->record->total_amount ?? 0;
        }

        if (blank($data['status'] ?? null)) {
            $data['status'] = $this->record->status ?? 'active';
        }

        return $data;
    }
}

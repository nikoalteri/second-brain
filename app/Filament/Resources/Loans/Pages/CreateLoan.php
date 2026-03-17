<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function authorizeAccess(): void
    {
        $this->authorize('create', static::$resource::getModel());
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validazione avanzata tramite FormRequest
        $request = app(\App\Http\Requests\StoreLoanRequest::class);
        $request->merge($data);
        $validated = app('validator')->make(
            $request->all(),
            (new \App\Http\Requests\StoreLoanRequest())->rules()
        )->validate();
        $data = array_merge($data, $validated);

        $data['user_id'] = auth()->id();

        if (blank($data['paid_installments'] ?? null)) {
            $data['paid_installments'] = 0;
        }

        if (blank($data['total_installments'] ?? null)) {
            $data['total_installments'] = 1;
        }

        if (blank($data['remaining_amount'] ?? null)) {
            $data['remaining_amount'] = $data['total_amount'] ?? 0;
        }

        if (blank($data['status'] ?? null)) {
            $data['status'] = 'active';
        }

        return $data;
    }
}

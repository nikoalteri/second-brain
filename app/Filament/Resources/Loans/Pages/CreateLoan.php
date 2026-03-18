<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Loans\Schemas\LoanForm;
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
        $data['user_id'] = auth()->id();

        if (blank($data['is_variable_rate'] ?? null)) {
            $data['is_variable_rate'] = false;
        }

        if (blank($data['paid_installments'] ?? null)) {
            $data['paid_installments'] = 0;
        }

        if (blank($data['total_installments'] ?? null)) {
            $data['total_installments'] = 1;
        }

        if (blank($data['status'] ?? null)) {
            $data['status'] = 'active';
        }

        $data['monthly_payment']  = LoanForm::computeMonthlyPayment($data);
        $data['remaining_amount'] = (float) ($data['total_amount'] ?? 0);

        return $data;
    }
}

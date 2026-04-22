<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Loans\Schemas\LoanForm;
use App\Services\LoanScheduleService;
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
        $data['user_id'] = $this->record->user_id ?? auth()->id();

        if (blank($data['paid_installments'] ?? null)) {
            $data['paid_installments'] = $this->record->paid_installments ?? 0;
        }

        if (blank($data['total_installments'] ?? null)) {
            $data['total_installments'] = $this->record->total_installments ?? 1;
        }

        if (blank($data['status'] ?? null)) {
            $data['status'] = $this->record->status ?? 'active';
        }

        $data['monthly_payment'] = LoanForm::computeMonthlyPayment($data);

        if ($data['is_variable_rate'] ?? false) {
            // Variable rate: remaining_amount is maintained by syncLoan() when payments change
            $data['remaining_amount'] = $this->record->remaining_amount;
        } else {
            $data['remaining_amount'] = LoanForm::computeRemainingAmount($data);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        app(LoanScheduleService::class)->generate($this->record, onlyMissing: true);
    }
}

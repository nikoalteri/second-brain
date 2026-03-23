<?php

namespace App\Filament\Resources\CreditCards\Pages;

use App\Filament\Resources\CreditCards\CreditCardResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditCreditCard extends EditRecord
{
    protected static string $resource = CreditCardResource::class;

    protected function authorizeAccess(): void
    {
        $this->authorize('update', $this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['user_id'] = $this->record->user_id ?? Auth::id();
        $data['stamp_duty_amount'] ??= 2;
        $data['current_balance'] ??= 0;

        return $data;
    }
}

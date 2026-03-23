<?php

namespace App\Filament\Resources\CreditCards\Pages;

use App\Filament\Resources\CreditCards\CreditCardResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCreditCard extends CreateRecord
{
    protected static string $resource = CreditCardResource::class;

    protected function authorizeAccess(): void
    {
        $this->authorize('create', static::$resource::getModel());
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['status'] ??= 'active';
        $data['current_balance'] ??= 0;
        $data['stamp_duty_amount'] ??= 2;
        $data['skip_weekends'] ??= true;

        return $data;
    }
}

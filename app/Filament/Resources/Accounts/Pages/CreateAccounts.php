<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountsResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccounts extends CreateRecord
{
    protected static string $resource = AccountsResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $openingBalance = (float) ($data['opening_balance'] ?? 0);
        $data['opening_balance'] = $openingBalance;
        $data['balance'] = $openingBalance;

        return $data;
    }

    protected function authorizeAccess(): void
    {
        $this->authorize('create', static::$resource::getModel());
    }
}

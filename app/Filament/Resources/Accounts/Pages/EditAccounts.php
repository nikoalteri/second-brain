<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountsResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAccounts extends EditRecord
{
    protected static string $resource = AccountsResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $openingBalance = (float) ($data['opening_balance'] ?? 0);
        $previousOpeningBalance = (float) ($this->record->opening_balance ?? 0);
        $currentBalance = (float) ($this->record->balance ?? 0);

        $data['opening_balance'] = $openingBalance;
        $data['balance'] = $currentBalance + ($openingBalance - $previousOpeningBalance);

        return $data;
    }

    protected function authorizeAccess(): void
    {
        $this->authorize('update', $this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

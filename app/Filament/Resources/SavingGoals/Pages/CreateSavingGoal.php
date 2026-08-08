<?php

namespace App\Filament\Resources\SavingGoals\Pages;

use App\Filament\Resources\SavingGoals\SavingGoalResource;
use App\Models\Account;
use Filament\Resources\Pages\CreateRecord;

class CreateSavingGoal extends CreateRecord
{
    protected static string $resource = SavingGoalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // The goal's owner follows the CHOSEN account, not the acting admin: Filament exempts
        // superadmin from HasUserScoping, so its account picker can list accounts across every
        // user.
        $data['user_id'] = Account::query()->findOrFail($data['account_id'])->user_id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

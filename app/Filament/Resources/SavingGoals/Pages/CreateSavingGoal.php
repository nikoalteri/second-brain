<?php

namespace App\Filament\Resources\SavingGoals\Pages;

use App\Filament\Resources\SavingGoals\SavingGoalResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSavingGoal extends CreateRecord
{
    protected static string $resource = SavingGoalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

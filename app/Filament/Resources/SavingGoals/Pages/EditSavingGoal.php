<?php

namespace App\Filament\Resources\SavingGoals\Pages;

use App\Filament\Resources\SavingGoals\SavingGoalResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSavingGoal extends EditRecord
{
    protected static string $resource = SavingGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

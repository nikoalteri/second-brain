<?php

namespace App\Filament\Resources\SubscriptionFrequencies\Pages;

use App\Filament\Resources\SubscriptionFrequencies\SubscriptionFrequencyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubscriptionFrequency extends EditRecord
{
    protected static string $resource = SubscriptionFrequencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

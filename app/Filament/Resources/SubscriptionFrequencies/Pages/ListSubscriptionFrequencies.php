<?php

namespace App\Filament\Resources\SubscriptionFrequencies\Pages;

use App\Filament\Resources\SubscriptionFrequencies\SubscriptionFrequencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionFrequencies extends ListRecords
{
    protected static string $resource = SubscriptionFrequencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

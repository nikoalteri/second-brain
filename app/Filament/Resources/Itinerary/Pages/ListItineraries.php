<?php

namespace App\Filament\Resources\Itinerary\Pages;

use App\Filament\Resources\ItineraryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItineraries extends ListRecords
{
    protected static string $resource = ItineraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

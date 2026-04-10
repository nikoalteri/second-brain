<?php

namespace App\Filament\Resources\Itinerary\Pages;

use App\Filament\Resources\ItineraryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateItinerary extends CreateRecord
{
    protected static string $resource = ItineraryResource::class;

    protected function authorizeAccess(): void
    {
        $this->authorize('create', static::$resource::getModel());
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }
}

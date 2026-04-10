<?php

namespace App\Filament\Resources\Itinerary\Pages;

use App\Filament\Resources\ItineraryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditItinerary extends EditRecord
{
    protected static string $resource = ItineraryResource::class;

    protected function authorizeAccess(): void
    {
        $this->authorize('update', $this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['user_id'] = $this->record->user_id ?? Auth::id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

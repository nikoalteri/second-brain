<?php

namespace App\Filament\Resources\Travel\Pages;

use App\Filament\Resources\TravelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditTrip extends EditRecord
{
    protected static string $resource = TravelResource::class;

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

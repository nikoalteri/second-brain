<?php

namespace App\Filament\Resources\Travel\Pages;

use App\Filament\Resources\TravelResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTrip extends CreateRecord
{
    protected static string $resource = TravelResource::class;

    protected function authorizeAccess(): void
    {
        $this->authorize('create', static::$resource::getModel());
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['status'] ??= 'planned';

        return $data;
    }
}

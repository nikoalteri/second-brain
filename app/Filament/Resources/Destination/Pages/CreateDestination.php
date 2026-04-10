<?php

namespace App\Filament\Resources\Destination\Pages;

use App\Filament\Resources\DestinationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDestination extends CreateRecord
{
    protected static string $resource = DestinationResource::class;

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

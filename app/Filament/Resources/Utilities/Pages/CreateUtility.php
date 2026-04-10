<?php

namespace App\Filament\Resources\Utilities\Pages;

use App\Filament\Resources\UtilityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUtility extends CreateRecord
{
    protected static string $resource = UtilityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}

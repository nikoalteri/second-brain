<?php

namespace App\Filament\Resources\HealthRecords\Pages;

use App\Filament\Resources\HealthRecords\HealthRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHealthRecords extends ListRecords
{
    protected static string $resource = HealthRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

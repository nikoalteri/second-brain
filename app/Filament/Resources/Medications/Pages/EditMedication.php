<?php

namespace App\Filament\Resources\Medications\Pages;

use App\Filament\Resources\Medications\MedicationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditMedication extends EditRecord
{
    protected static string $resource = MedicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\BloodTests\Pages;

use App\Filament\Resources\BloodTests\BloodTestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBloodTest extends EditRecord
{
    protected static string $resource = BloodTestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

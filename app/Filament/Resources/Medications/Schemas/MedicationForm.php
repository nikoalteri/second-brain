<?php

namespace App\Filament\Resources\Medications\Schemas;

use App\Enums\MedicationDosageUnit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MedicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('dosage')
                    ->numeric()
                    ->step(0.01)
                    ->required(),
                Select::make('dosage_unit')
                    ->options(MedicationDosageUnit::class)
                    ->required(),
                TextInput::make('frequency')
                    ->required(),
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('end_date'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

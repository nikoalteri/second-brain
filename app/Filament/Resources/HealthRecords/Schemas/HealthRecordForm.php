<?php

namespace App\Filament\Resources\HealthRecords\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HealthRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('date')
                    ->required(),
                TextInput::make('weight')
                    ->numeric(),
                TextInput::make('height')
                    ->numeric(),
                TextInput::make('heart_rate')
                    ->numeric(),
                TextInput::make('blood_pressure_systolic')
                    ->numeric(),
                TextInput::make('blood_pressure_diastolic')
                    ->numeric(),
                TextInput::make('temperature')
                    ->numeric(),
                TextInput::make('notes'),
            ]);
    }
}

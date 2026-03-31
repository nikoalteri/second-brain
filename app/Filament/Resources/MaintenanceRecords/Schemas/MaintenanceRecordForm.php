<?php

namespace App\Filament\Resources\MaintenanceRecords\Schemas;

use App\Enums\MaintenanceRecordType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MaintenanceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('vehicle_id')
                    ->relationship('vehicle', 'license_plate')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('type')
                    ->options(MaintenanceRecordType::class)
                    ->required(),
                DatePicker::make('maintenance_date')
                    ->required(),
                TextInput::make('cost')
                    ->numeric()
                    ->step(0.01)
                    ->required(),
                TextInput::make('mileage')
                    ->numeric(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}

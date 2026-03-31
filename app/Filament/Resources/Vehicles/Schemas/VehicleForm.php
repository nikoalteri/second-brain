<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Enums\VehicleType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('make')
                    ->required(),
                TextInput::make('model')
                    ->required(),
                TextInput::make('year')
                    ->numeric()
                    ->required(),
                Select::make('type')
                    ->options(VehicleType::class)
                    ->required(),
                TextInput::make('license_plate')
                    ->required()
                    ->unique('vehicles', 'license_plate'),
                DatePicker::make('purchase_date'),
                TextInput::make('purchase_price')
                    ->numeric()
                    ->step(0.01),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

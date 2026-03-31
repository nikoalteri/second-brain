<?php

namespace App\Filament\Resources\Flights\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class FlightForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('trip_id')
                    ->required()
                    ->numeric(),
                TextInput::make('airline')
                    ->required(),
                TextInput::make('flight_number')
                    ->required(),
                DatePicker::make('departure_date')
                    ->required(),
                TimePicker::make('departure_time')
                    ->required(),
                DatePicker::make('arrival_date')
                    ->required(),
                TimePicker::make('arrival_time')
                    ->required(),
                TextInput::make('departure_airport')
                    ->required(),
                TextInput::make('arrival_airport')
                    ->required(),
                TextInput::make('seat'),
            ]);
    }
}

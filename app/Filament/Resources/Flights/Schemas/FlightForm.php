<?php

namespace App\Filament\Resources\Flights\Schemas;

use App\Enums\BookingStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class FlightForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('trip_id')
                    ->relationship('trip', 'destination')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('airline')
                    ->required(),
                TextInput::make('flight_number')
                    ->required(),
                DateTimePicker::make('departure_time')
                    ->required(),
                DateTimePicker::make('arrival_time')
                    ->required(),
                TextInput::make('departure_airport')
                    ->required(),
                TextInput::make('arrival_airport')
                    ->required(),
                TextInput::make('price')
                    ->numeric()
                    ->step(0.01)
                    ->required(),
                Select::make('status')
                    ->options(BookingStatus::class)
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

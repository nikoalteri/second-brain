<?php

namespace App\Filament\Resources\Hotels\Schemas;

use App\Enums\BookingStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class HotelForm
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
                TextInput::make('name')
                    ->required(),
                TextInput::make('location')
                    ->required(),
                DatePicker::make('check_in_date')
                    ->required(),
                DatePicker::make('check_out_date')
                    ->required(),
                TextInput::make('number_of_rooms')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('price_per_night')
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

<?php

namespace App\Filament\Resources\Hotels\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HotelForm
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
                TextInput::make('name')
                    ->required(),
                TextInput::make('city')
                    ->required(),
                DatePicker::make('check_in_date')
                    ->required(),
                DatePicker::make('check_out_date')
                    ->required(),
                TextInput::make('nights')
                    ->required()
                    ->numeric(),
                TextInput::make('cost_per_night')
                    ->numeric(),
                TextInput::make('total_cost')
                    ->numeric()
                    ->prefix('$'),
            ]);
    }
}

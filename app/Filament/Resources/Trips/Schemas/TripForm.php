<?php

namespace App\Filament\Resources\Trips\Schemas;

use App\Enums\TripStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('destination')
                    ->required(),
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('end_date')
                    ->required(),
                Select::make('status')
                    ->options(TripStatus::class)
                    ->required(),
                TextInput::make('budget')
                    ->numeric()
                    ->step(0.01),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

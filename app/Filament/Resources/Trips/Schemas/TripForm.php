<?php

namespace App\Filament\Resources\Trips\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('destination')
                    ->required(),
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('end_date')
                    ->required(),
                Select::make('trip_type')
                    ->options(['vacation' => 'Vacation', 'business' => 'Business', 'adventure' => 'Adventure'])
                    ->default('vacation')
                    ->required(),
                Select::make('status')
                    ->options(['planned' => 'Planned', 'in_progress' => 'In progress', 'completed' => 'Completed'])
                    ->default('planned')
                    ->required(),
                TextInput::make('budget')
                    ->numeric(),
                TextInput::make('total_spent')
                    ->numeric(),
            ]);
    }
}

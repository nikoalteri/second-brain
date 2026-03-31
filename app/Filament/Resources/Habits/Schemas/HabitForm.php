<?php

namespace App\Filament\Resources\Habits\Schemas;

use App\Enums\HabitFrequency;
use App\Enums\HabitDifficulty;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class HabitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('frequency')
                    ->options(HabitFrequency::class)
                    ->required(),
                Select::make('difficulty')
                    ->options(HabitDifficulty::class)
                    ->required(),
                DatePicker::make('start_date'),
                DatePicker::make('end_date'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

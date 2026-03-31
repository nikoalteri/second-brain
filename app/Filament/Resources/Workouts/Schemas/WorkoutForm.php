<?php

namespace App\Filament\Resources\Workouts\Schemas;

use App\Enums\WorkoutType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class WorkoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(WorkoutType::class)
                    ->required(),
                DatePicker::make('date')
                    ->required(),
                TextInput::make('duration_minutes')
                    ->numeric()
                    ->label('Duration (minutes)'),
                TextInput::make('calories_burned')
                    ->numeric(),
                TextInput::make('distance')
                    ->numeric()
                    ->step(0.01),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

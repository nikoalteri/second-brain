<?php

namespace App\Filament\Resources\Goals\Schemas;

use App\Enums\GoalCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class GoalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Select::make('category')
                    ->options(GoalCategory::class)
                    ->required(),
                DatePicker::make('start_date'),
                DatePicker::make('target_date'),
                TextInput::make('target_value')
                    ->numeric()
                    ->step(0.01),
                TextInput::make('current_value')
                    ->numeric()
                    ->step(0.01),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

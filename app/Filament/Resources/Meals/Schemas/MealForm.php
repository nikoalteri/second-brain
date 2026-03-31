<?php

namespace App\Filament\Resources\Meals\Schemas;

use App\Enums\MealType;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('meal_type')
                    ->options(MealType::class)
                    ->required(),
                DatePicker::make('date_eaten')
                    ->required(),
                Select::make('recipe_id')
                    ->relationship('recipe', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('calories')
                    ->numeric(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Checkbox::make('is_favorite')
                    ->label('Mark as favorite'),
            ]);
    }
}

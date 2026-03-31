<?php

namespace App\Filament\Resources\Meals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('recipe_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('date_eaten')
                    ->required(),
                TextInput::make('rating')
                    ->numeric(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Toggle::make('is_favorite')
                    ->required(),
            ]);
    }
}

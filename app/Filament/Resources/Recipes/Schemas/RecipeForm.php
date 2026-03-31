<?php

namespace App\Filament\Resources\Recipes\Schemas;

use App\Enums\RecipeCuisine;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('cuisine')
                    ->options(RecipeCuisine::class),
                TextInput::make('prep_time_minutes')
                    ->numeric()
                    ->label('Prep Time (minutes)'),
                TextInput::make('cook_time_minutes')
                    ->numeric()
                    ->label('Cook Time (minutes)'),
                TextInput::make('servings')
                    ->numeric(),
                Textarea::make('instructions')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}

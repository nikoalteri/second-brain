<?php

namespace App\Filament\Resources\Recipes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                Select::make('cuisine')
                    ->options([
            'italian' => 'Italian',
            'asian' => 'Asian',
            'mexican' => 'Mexican',
            'mediterranean' => 'Mediterranean',
            'other' => 'Other',
        ])
                    ->default('other')
                    ->required(),
                Select::make('difficulty')
                    ->options(['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'])
                    ->default('medium')
                    ->required(),
                TextInput::make('prep_time')
                    ->numeric(),
                TextInput::make('cook_time')
                    ->numeric(),
                TextInput::make('servings')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('ingredients_list'),
            ]);
    }
}

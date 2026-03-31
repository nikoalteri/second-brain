<?php

namespace App\Filament\Resources\Ingredients\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IngredientForm
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
                Select::make('unit')
                    ->options(['g' => 'G', 'ml' => 'Ml', 'tbsp' => 'Tbsp', 'cup' => 'Cup', 'piece' => 'Piece'])
                    ->default('piece')
                    ->required(),
                Select::make('category')
                    ->options([
            'vegetable' => 'Vegetable',
            'meat' => 'Meat',
            'grain' => 'Grain',
            'dairy' => 'Dairy',
            'spice' => 'Spice',
            'other' => 'Other',
        ])
                    ->default('other')
                    ->required(),
            ]);
    }
}

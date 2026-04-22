<?php

namespace App\Filament\Resources\SubscriptionFrequencies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubscriptionFrequencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('months_interval')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->step(1),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->step(1)
                    ->default(1),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}

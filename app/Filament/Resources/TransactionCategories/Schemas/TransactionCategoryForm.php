<?php

namespace App\Filament\Resources\TransactionCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TransactionCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Select::make('scope')
                    ->options([
                        'generic' => 'Generic',
                        'subscription' => 'Subscription',
                    ])
                    ->default('generic')
                    ->required(),

                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}

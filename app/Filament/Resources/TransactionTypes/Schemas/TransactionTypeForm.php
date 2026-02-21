<?php

namespace App\Filament\Resources\TransactionTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TransactionTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('color')
                    ->required(),
                TextInput::make('icon')
                    ->required(),
                Toggle::make('is_income')
                    ->required(),
            ]);
    }
}

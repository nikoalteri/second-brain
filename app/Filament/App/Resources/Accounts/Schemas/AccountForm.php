<?php

namespace App\Filament\App\Resources\Accounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AccountForm
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
                Select::make('type')
                    ->options([
            'bank' => 'Bank',
            'cash' => 'Cash',
            'investment' => 'Investment',
            'emergency_fund' => 'Emergency fund',
            'debt' => 'Debt',
        ])
                    ->required(),
                TextInput::make('balance')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('currency')
                    ->required()
                    ->default('EUR'),
                TextInput::make('color'),
                TextInput::make('icon'),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_debt')
                    ->required(),
            ]);
    }
}

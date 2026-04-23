<?php

namespace App\Filament\Resources\Accounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use App\Models\User;

class AccountsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('ID')
                    ->options(fn() => User::pluck('name', 'id')->toArray())
                    ->required(),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Type')
                    ->options([
                        'bank' => 'Bank',
                        'cash' => 'Cash',
                        'investment' => 'Investment',
                        'emergency_fund' => 'Emergency Fund',
                        'debt' => 'Debt',
                    ])
                    ->required(),
                TextInput::make('balance')
                    ->label('Current Balance')
                    ->numeric()
                    ->prefix('€')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('opening_balance')
                    ->label('Opening Balance')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->minValue(null)
                    ->rules(['nullable', 'numeric']),
                TextInput::make('currency')
                    ->label('Currency')
                    ->required()
                    ->default('EUR'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}

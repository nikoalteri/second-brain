<?php

namespace App\Filament\Resources\Accounts\Schemas;

use Filament\Forms\Components\ColorPicker;
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
                    ->label('Utente')
                    ->options(fn() => User::pluck('name', 'id')->toArray())
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
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
                    ->label('Saldo attuale')
                    ->numeric()
                    ->prefix('€')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Calcolato automaticamente dal sistema'),
                TextInput::make('opening_balance')
                    ->label('Saldo iniziale')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->minValue(null)  // ✅ rimuove validazione min=0
                    ->rules(['nullable', 'numeric']) // ✅ permette negativi
                    ->helperText('Saldo al momento dell\'adozione del sistema'),
                TextInput::make('currency')
                    ->required()
                    ->default('EUR'),
                ColorPicker::make('color')
                    ->default('#000000'),
                TextInput::make('icon'),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}

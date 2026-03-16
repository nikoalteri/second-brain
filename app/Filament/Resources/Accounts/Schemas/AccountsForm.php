<?php

namespace App\Filament\Resources\Accounts\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Components\Builder;
use Filament\Schemas\Schema;
use App\Models\User;

class AccountsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_name')
                    ->label('Utente')
                    ->options(fn() => User::pluck('name', 'name')->toArray())
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
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('€'),
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
                    ->required(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\CreditCards\Schemas;

use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CreditCardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Anagrafica')
                    ->components([
                        TextInput::make('name')
                            ->label('Name')
                            ->helperText('Use a clear name, e.g. Bank + card nickname.')
                            ->required()
                            ->maxLength(255),
                        Select::make('account_id')
                            ->label('Settlement account')
                            ->relationship(
                                name: 'account',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->when(
                                        ! Auth::user()?->hasRole('superadmin'),
                                        fn(Builder $query) => $query->where('user_id', Auth::id())
                                    )
                                    ->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('This account will be charged when card payments are posted.')
                            ->required(),
                        Select::make('type')
                            ->label('Type')
                            ->options(CreditCardType::class)
                            ->helperText('Charge: full statement payment. Revolving: fixed monthly installment with interest.')
                            ->required()
                            ->default(CreditCardType::CHARGE->value)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if ($state === CreditCardType::CHARGE->value) {
                                    $set('fixed_payment', null);
                                    $set('interest_rate', null);
                                }
                            }),
                        Select::make('status')
                            ->label('Status')
                            ->options(CreditCardStatus::class)
                            ->helperText('Set Active to include the card in cycle generation and KPIs.')
                            ->required()
                            ->default(CreditCardStatus::ACTIVE->value),
                        DatePicker::make('start_date')
                            ->label('Start date')
                            ->helperText('Optional. If set, cycles and expenses should start from this date.'),
                    ])
                    ->columns(2),
                Section::make('Regole')
                    ->components([
                        TextInput::make('credit_limit')
                            ->label('Credit limit')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->helperText('Maximum facility for the card. Leave empty for unlimited credit.')
                            ->nullable(),
                        TextInput::make('fixed_payment')
                            ->label('Max monthly installment')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->helperText('Maximum monthly amount for revolving cards. If residual balance is lower, the generated installment is reduced automatically.')
                            ->nullable()
                            ->live()
                            ->disabled(fn (callable $get) => $get('type') !== CreditCardType::REVOLVING->value),
                        TextInput::make('interest_rate')
                            ->label('Interest rate (%)')
                            ->numeric()
                            ->suffix('%')
                            ->step(0.01)
                            ->helperText('Nominal monthly interest rate applied to revolving residual balance.')
                            ->nullable()
                            ->live()
                            ->disabled(fn (callable $get) => $get('type') !== CreditCardType::REVOLVING->value),
                        TextInput::make('stamp_duty_amount')
                            ->label('Stamp duty')
                            ->numeric()
                            ->prefix('€')
                            ->default(0)
                            ->step(0.01)
                            ->helperText('Fixed fee added to each statement (if applicable).')
                            ->required(),
                        TextInput::make('statement_day')
                            ->label('Statement day')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->helperText('Day of month when the statement cycle is closed.')
                            ->required(),
                        TextInput::make('due_day')
                            ->label('Due day')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->nullable()
                            ->helperText('Optional: leave empty if there is no due date'),
                        Toggle::make('skip_weekends')
                            ->label('Skip weekends')
                            ->helperText('If enabled, generated dates are shifted away from Saturdays and Sundays.')
                            ->default(true),
                        TextInput::make('current_balance')
                            ->label('Current balance')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->default(0)
                            ->helperText('Used credit (outstanding principal). Available credit is limit minus this value.')
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}

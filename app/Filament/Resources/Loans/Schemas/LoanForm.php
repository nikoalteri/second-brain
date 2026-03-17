<?php

namespace App\Filament\Resources\Loans\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\LoanStatus;

class LoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Main data')
                    ->components([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Select::make('account_id')
                            ->label('Account')
                            ->relationship(
                                name: 'account',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->where('user_id', auth()->id())
                                    ->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('total_amount')
                            ->label('Importo totale')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set) => $set('remaining_amount', $state)),
                        TextInput::make('monthly_payment')
                            ->label('Monthly Payment')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->required(),
                        TextInput::make('remaining_amount')
                            ->label('Remaining Amount')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Schedule')
                    ->components([
                        TextInput::make('withdrawal_day')
                            ->label('Withdrawal Day')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->required(),
                        Toggle::make('skip_weekends')
                            ->label('Skip Weekends')
                            ->default(true)
                            ->inline(false),
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('End Date'),
                        TextInput::make('total_installments')
                            ->label('Total Installments')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        TextInput::make('paid_installments')
                            ->label('Paid Installments')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Select::make('status')
                            ->options(LoanStatus::class)
                            ->default(LoanStatus::ACTIVE->value)
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}

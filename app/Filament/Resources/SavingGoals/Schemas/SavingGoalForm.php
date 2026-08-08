<?php

namespace App\Filament\Resources\SavingGoals\Schemas;

use App\Enums\SavingGoalStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SavingGoalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Goal')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Emergency fund, New car'),

                        Select::make('account_id')
                            ->label('Account')
                            ->relationship('account', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Progress tracks this account\'s real balance'),

                        TextInput::make('target_amount')
                            ->label('Target amount')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->minValue(0.01)
                            ->required(),

                        DatePicker::make('target_date')
                            ->native(false),

                        Select::make('status')
                            ->options(SavingGoalStatus::class)
                            ->default(SavingGoalStatus::ACTIVE)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

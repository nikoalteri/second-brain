<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Subscription Info')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Netflix, Spotify'),

                        Select::make('frequency')
                            ->options(SubscriptionFrequency::class)
                            ->required()
                            ->live(),
                    ])
                    ->columns(2),

                Section::make('Cost')
                    ->schema([
                        TextInput::make('monthly_cost')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->helperText('For monthly subscriptions'),

                        TextInput::make('annual_cost')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->helperText('For annual/biennial subscriptions'),
                    ])
                    ->columns(2),

                Section::make('Renewal')
                    ->schema([
                        TextInput::make('day_of_month')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->default(1)
                            ->helperText('Day of month for renewal'),

                        DatePicker::make('next_renewal_date')
                            ->required()
                            ->helperText('Next scheduled renewal'),
                    ])
                    ->columns(2),

                Section::make('Account & Category')
                    ->schema([
                        Select::make('account_id')
                            ->relationship('account', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Account to debit'),

                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Transaction category (optional)'),
                    ])
                    ->columns(2),

                Section::make('Settings')
                    ->schema([
                        Select::make('status')
                            ->options(SubscriptionStatus::class)
                            ->required()
                            ->default(SubscriptionStatus::ACTIVE),

                        Toggle::make('auto_create_transaction')
                            ->default(false)
                            ->helperText('Auto-create transaction on renewal'),
                    ])
                    ->columns(2),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

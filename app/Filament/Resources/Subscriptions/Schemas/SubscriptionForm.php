<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionFrequency;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
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

                        Select::make('subscription_frequency_id')
                            ->label('Frequency')
                            ->relationship('frequencyOption', 'name', fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'))
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Section::make('Cost')
                    ->schema([
                        TextInput::make('annual_cost')
                            ->label('Renewal amount')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->required(),
                        TextInput::make('monthly_cost')
                            ->label('Monthly equivalent')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Section::make('Renewal')
                    ->schema([
                        TextInput::make('day_of_month')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->default(1),

                        DatePicker::make('next_renewal_date')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Payment Source & Category')
                    ->schema([
                        Select::make('account_id')
                            ->relationship('account', 'name')
                            ->searchable()
                            ->preload()
                            ->requiredWithout('credit_card_id')
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $state ? $set('credit_card_id', null) : null),

                        Select::make('credit_card_id')
                            ->relationship('creditCard', 'name')
                            ->searchable()
                            ->preload()
                            ->requiredWithout('account_id')
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $state ? $set('account_id', null) : null),

                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(3),

                Section::make('Settings')
                    ->schema([
                        Select::make('status')
                            ->options(SubscriptionStatus::class)
                            ->required()
                            ->default(SubscriptionStatus::ACTIVE),

                        Toggle::make('auto_create_transaction')
                            ->default(false),
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

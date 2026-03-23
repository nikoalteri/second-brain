<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use Filament\Schemas\Components\DatePickerField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\SelectField;
use Filament\Schemas\Components\TextInputField;
use Filament\Schemas\Components\TextareaField;
use Filament\Schemas\Components\ToggleField;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Subscription Info')
                    ->schema([
                        TextInputField::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Netflix, Spotify'),

                        SelectField::make('frequency')
                            ->options(SubscriptionFrequency::class)
                            ->required()
                            ->live(),
                    ])
                    ->columns(2),

                Section::make('Cost')
                    ->schema([
                        TextInputField::make('monthly_cost')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->helperText('For monthly subscriptions'),

                        TextInputField::make('annual_cost')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->helperText('For annual/biennial subscriptions'),
                    ])
                    ->columns(2),

                Section::make('Renewal')
                    ->schema([
                        TextInputField::make('day_of_month')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->default(1)
                            ->helperText('Day of month for renewal'),

                        DatePickerField::make('next_renewal_date')
                            ->required()
                            ->helperText('Next scheduled renewal'),
                    ])
                    ->columns(2),

                Section::make('Account & Category')
                    ->schema([
                        SelectField::make('account_id')
                            ->relationship('account', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Account to debit'),

                        SelectField::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Transaction category (optional)'),
                    ])
                    ->columns(2),

                Section::make('Settings')
                    ->schema([
                        SelectField::make('status')
                            ->options(SubscriptionStatus::class)
                            ->required()
                            ->default(SubscriptionStatus::ACTIVE),

                        ToggleField::make('auto_create_transaction')
                            ->default(false)
                            ->helperText('Auto-create transaction on renewal'),
                    ])
                    ->columns(2),

                Section::make('Notes')
                    ->schema([
                        TextareaField::make('notes')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

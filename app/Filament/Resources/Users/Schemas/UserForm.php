<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\PhoneNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal data')
                    ->columns(2)
                    ->components([
                        TextInput::make('first_name')
                            ->label('First name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('last_name')
                            ->label('Last name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'users', column: 'email', ignoreRecord: true),

                        Select::make('phone_country_code')
                            ->label('Phone prefix')
                            ->options(PhoneNumber::countryCodeOptions())
                            ->default(PhoneNumber::DEFAULT_COUNTRY_CODE)
                            ->searchable()
                            ->dehydrated(false),

                        TextInput::make('phone_number')
                            ->label('Phone number')
                            ->tel()
                            ->maxLength(20)
                            ->dehydrated(false),

                        TextInput::make('tax_code')
                            ->label('Tax code')
                            ->maxLength(16)
                            ->unique(table: 'users', column: 'tax_code', ignoreRecord: true),

                        DatePicker::make('date_of_birth')
                            ->label('Date of birth')
                            ->native(false)
                            ->maxDate(now()->subDay()),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->rules(['confirmed']),

                        TextInput::make('password_confirmation')
                            ->label('Confirm password')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrated(false),
                    ]),

                Section::make('Permissions')
                    ->components([
                        Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload()
                            ->searchable(),
                    ]),

                Section::make('Account status')
                    ->components([
                        Toggle::make('is_active')
                            ->label('Active account')
                            ->default(true),
                    ]),
            ]);
    }
}

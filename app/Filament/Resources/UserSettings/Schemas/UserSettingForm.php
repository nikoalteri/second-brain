<?php

namespace App\Filament\Resources\UserSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Select::make('setting_key')
                    ->options([
            'theme' => 'Theme',
            'language' => 'Language',
            'notifications' => 'Notifications',
            'privacy' => 'Privacy',
        ])
                    ->default('theme')
                    ->required(),
                TextInput::make('setting_value')
                    ->required(),
            ]);
    }
}

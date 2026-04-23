<?php

namespace App\Filament\Resources\UserSettings\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('setting_key')
                    ->label('Setting key')
                    ->options([
                        'theme' => 'Theme',
                        'language' => 'Language',
                        'notifications' => 'Notifications',
                        'privacy' => 'Privacy',
                    ])
                    ->required(),
                TextInput::make('setting_value')
                    ->label('Setting value')
                    ->helperText('Examples: dark, en, enabled, private')
                    ->maxLength(255),
            ]);
    }
}

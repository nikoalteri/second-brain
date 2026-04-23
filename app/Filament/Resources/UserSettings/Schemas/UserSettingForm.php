<?php

namespace App\Filament\Resources\UserSettings\Schemas;

use App\Models\UserSetting;
use App\Models\User;
use Filament\Forms\Components\Select;
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
                    ->options(UserSetting::keyLabels())
                    ->live()
                    ->required(),
                Select::make('setting_value')
                    ->label('Setting value')
                    ->options(fn (callable $get): array => UserSetting::optionsFor((string) $get('setting_key')))
                    ->disabled(fn (callable $get): bool => blank($get('setting_key')))
                    ->required(),
            ]);
    }
}

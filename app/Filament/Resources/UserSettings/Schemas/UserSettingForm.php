<?php

namespace App\Filament\Resources\UserSettings\Schemas;

use App\Enums\UITheme;
use App\Enums\PrivacyLevel;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class UserSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('theme')
                    ->options(UITheme::class)
                    ->required(),
                Select::make('privacy_level')
                    ->options(PrivacyLevel::class)
                    ->required(),
                Checkbox::make('notifications_enabled')
                    ->label('Enable email notifications'),
                Checkbox::make('two_factor_enabled')
                    ->label('Enable two-factor authentication'),
            ]);
    }
}

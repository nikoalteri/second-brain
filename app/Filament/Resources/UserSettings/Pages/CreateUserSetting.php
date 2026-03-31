<?php

namespace App\Filament\Resources\UserSettings\Pages;

use App\Filament\Resources\UserSettings\UserSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserSetting extends CreateRecord
{
    protected static string $resource = UserSettingResource::class;
}

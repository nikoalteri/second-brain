<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSetting;
use InvalidArgumentException;

class UserSettingsService
{
    public function update(User $user, array $settings): User
    {
        foreach ($settings as $key => $value) {
            if (! in_array($key, UserSetting::activeKeys(), true)) {
                throw new InvalidArgumentException("Unsupported user setting [{$key}].");
            }

            $setting = $user->userSettings()
                ->withTrashed()
                ->firstOrNew(['setting_key' => $key]);

            $setting->setting_value = UserSetting::normalizeValue($key, $value);
            $setting->deleted_at = null;
            $setting->save();
        }

        $user->load('userSettings');

        return $user;
    }
}

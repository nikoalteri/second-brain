<?php

namespace App\Support;

use App\Models\UserSetting;
use Illuminate\Support\Facades\Auth;

/**
 * Display-only currency formatting. Every amount is stored and calculated in EUR — this only
 * changes the symbol/separators shown to the current user, never the underlying figure.
 */
class Money
{
    public const LOCALES = [
        'EUR' => 'it',
        'CZK' => 'cs',
        'USD' => 'en_US',
        'GBP' => 'en_GB',
        'CHF' => 'de_CH',
    ];

    public static function currency(): string
    {
        $user = Auth::user();

        if (! $user) {
            return UserSetting::defaultFor(UserSetting::KEY_DISPLAY_CURRENCY);
        }

        return $user->resolvedSettings()[UserSetting::KEY_DISPLAY_CURRENCY]
            ?? UserSetting::defaultFor(UserSetting::KEY_DISPLAY_CURRENCY);
    }

    public static function locale(): string
    {
        return self::LOCALES[self::currency()] ?? 'en';
    }
}

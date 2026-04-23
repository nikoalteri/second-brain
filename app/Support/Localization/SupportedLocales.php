<?php

namespace App\Support\Localization;

final class SupportedLocales
{
    public const EN = 'en';
    public const IT = 'it';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::EN,
            self::IT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::EN => 'English',
            self::IT => 'Italiano',
        ];
    }

    public static function default(): string
    {
        return self::EN;
    }

    public static function appLocale(?string $value): string
    {
        return in_array($value, self::values(), true)
            ? $value
            : self::default();
    }

    public static function browserLocale(?string $value): string
    {
        return self::appLocale($value) === self::IT
            ? 'it-IT'
            : 'en-US';
    }
}

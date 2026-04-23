<?php

namespace App\Models;

use App\Support\Localization\SupportedLocales;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class UserSetting extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    public const KEY_THEME = 'theme';
    public const KEY_LANGUAGE = 'language';
    public const KEY_NOTIFICATIONS = 'notifications';
    public const KEY_PRIVACY = 'privacy';

    public const DEFAULTS = [
        self::KEY_THEME => 'system',
        self::KEY_LANGUAGE => 'en',
        self::KEY_NOTIFICATIONS => 'all',
        self::KEY_PRIVACY => 'visible',
    ];

    public const VALUE_OPTIONS = [
        self::KEY_THEME => [
            'light' => 'Light',
            'dark' => 'Dark',
            'system' => 'System',
        ],
        self::KEY_LANGUAGE => [],
        self::KEY_NOTIFICATIONS => [
            'all' => 'All toasts',
            'important_only' => 'Errors only',
        ],
        self::KEY_PRIVACY => [
            'visible' => 'Show profile details',
            'private' => 'Hide email and user ID',
        ],
    ];

    public const KEY_LABELS = [
        self::KEY_THEME => 'Theme',
        self::KEY_LANGUAGE => 'Language',
        self::KEY_NOTIFICATIONS => 'Notifications',
        self::KEY_PRIVACY => 'Privacy',
    ];

    protected $fillable = [
        'user_id',
        'setting_key',
        'setting_value',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function activeKeys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    public static function keyLabels(): array
    {
        return self::KEY_LABELS;
    }

    public static function optionsFor(string $key): array
    {
        if ($key === self::KEY_LANGUAGE) {
            return SupportedLocales::labels();
        }

        return self::VALUE_OPTIONS[$key] ?? [];
    }

    public static function defaultFor(string $key): ?string
    {
        return self::DEFAULTS[$key] ?? null;
    }

    public static function normalizeValue(string $key, ?string $value): string
    {
        if ($key === self::KEY_LANGUAGE) {
            return SupportedLocales::appLocale($value);
        }

        $options = self::optionsFor($key);

        if ($options === []) {
            throw new InvalidArgumentException("Unsupported user setting [{$key}].");
        }

        if ($value !== null && array_key_exists($value, $options)) {
            return $value;
        }

        return self::DEFAULTS[$key];
    }

    public static function labelFor(string $key, ?string $value): string
    {
        return self::optionsFor($key)[$value ?? ''] ?? self::normalizeValue($key, $value);
    }

}

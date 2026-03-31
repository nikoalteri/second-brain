<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RecipeCuisine: string implements HasLabel, HasColor
{
    case ITALIAN = 'italian';
    case FRENCH = 'french';
    case ASIAN = 'asian';
    case AMERICAN = 'american';
    case MEXICAN = 'mexican';
    case SPANISH = 'spanish';
    case GREEK = 'greek';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ITALIAN => 'Italian 🇮🇹',
            self::FRENCH => 'French 🇫🇷',
            self::ASIAN => 'Asian 🥢',
            self::AMERICAN => 'American 🇺🇸',
            self::MEXICAN => 'Mexican 🌮',
            self::SPANISH => 'Spanish 🇪🇸',
            self::GREEK => 'Greek 🇬🇷',
            self::OTHER => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ITALIAN => 'danger',
            self::FRENCH => 'info',
            self::ASIAN => 'warning',
            self::AMERICAN => 'gray',
            self::MEXICAN => 'success',
            self::SPANISH => 'warning',
            self::GREEK => 'info',
            self::OTHER => 'gray',
        };
    }
}

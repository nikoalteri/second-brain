<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PrivacyLevel: string implements HasLabel, HasColor
{
    case PUBLIC = 'public';
    case FRIENDS = 'friends';
    case PRIVATE = 'private';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PUBLIC => 'Public 🌐',
            self::FRIENDS => 'Friends 👥',
            self::PRIVATE => 'Private 🔒',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PUBLIC => 'warning',
            self::FRIENDS => 'info',
            self::PRIVATE => 'danger',
        };
    }
}

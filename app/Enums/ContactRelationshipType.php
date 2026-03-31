<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactRelationshipType: string implements HasLabel, HasColor
{
    case FAMILY = 'family';
    case FRIEND = 'friend';
    case COLLEAGUE = 'colleague';
    case BUSINESS = 'business';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FAMILY => 'Family',
            self::FRIEND => 'Friend',
            self::COLLEAGUE => 'Colleague',
            self::BUSINESS => 'Business',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FAMILY => 'danger',
            self::FRIEND => 'success',
            self::COLLEAGUE => 'info',
            self::BUSINESS => 'warning',
        };
    }
}

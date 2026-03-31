<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NotePriority: string implements HasLabel, HasColor
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::LOW => 'gray',
            self::MEDIUM => 'warning',
            self::HIGH => 'danger',
        };
    }
}

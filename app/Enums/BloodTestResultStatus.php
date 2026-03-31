<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BloodTestResultStatus: string implements HasLabel, HasColor
{
    case NORMAL = 'normal';
    case LOW = 'low';
    case HIGH = 'high';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::LOW => 'Low',
            self::HIGH => 'High',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NORMAL => 'success',
            self::LOW => 'warning',
            self::HIGH => 'danger',
        };
    }
}

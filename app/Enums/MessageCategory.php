<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MessageCategory: string implements HasLabel, HasColor
{
    case PERSONAL = 'personal';
    case WORK = 'work';
    case URGENT = 'urgent';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PERSONAL => 'Personal',
            self::WORK => 'Work',
            self::URGENT => 'Urgent',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PERSONAL => 'info',
            self::WORK => 'success',
            self::URGENT => 'danger',
        };
    }
}

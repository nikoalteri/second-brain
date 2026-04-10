<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ActivityType: string implements HasLabel, HasColor
{
    case SIGHTSEEING = 'sightseeing';
    case DINING = 'dining';
    case TRANSPORT = 'transport';
    case LODGING = 'lodging';
    case ENTERTAINMENT = 'entertainment';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SIGHTSEEING => 'Sightseeing',
            self::DINING => 'Dining',
            self::TRANSPORT => 'Transport',
            self::LODGING => 'Lodging',
            self::ENTERTAINMENT => 'Entertainment',
            self::OTHER => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SIGHTSEEING => 'info',
            self::DINING => 'success',
            self::TRANSPORT => 'warning',
            self::LODGING => 'primary',
            self::ENTERTAINMENT => 'danger',
            self::OTHER => 'gray',
        };
    }
}

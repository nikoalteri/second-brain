<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HabitFrequency: string implements HasLabel, HasColor
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DAILY => 'success',
            self::WEEKLY => 'info',
            self::MONTHLY => 'warning',
        };
    }
}

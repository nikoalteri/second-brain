<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasLabel, HasColor
{
    case BOOKED = 'booked';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BOOKED => 'Booked',
            self::CANCELLED => 'Cancelled',
            self::COMPLETED => 'Completed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BOOKED => 'info',
            self::CANCELLED => 'danger',
            self::COMPLETED => 'success',
        };
    }
}

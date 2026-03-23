<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CreditCardCycleStatus: string implements HasLabel, HasColor
{
    case OPEN = 'open';
    case ISSUED = 'issued';
    case PAID = 'paid';
    case OVERDUE = 'overdue';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::ISSUED => 'Issued',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPEN => 'info',
            self::ISSUED => 'warning',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
        };
    }
}

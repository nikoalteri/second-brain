<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CreditCardType: string implements HasLabel, HasColor
{
    case CHARGE = 'charge';
    case REVOLVING = 'revolving';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CHARGE => 'Charge',
            self::REVOLVING => 'Revolving',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CHARGE => 'info',
            self::REVOLVING => 'warning',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CardBrand: string implements HasLabel, HasColor
{
    case VISA = 'visa';
    case MASTERCARD = 'mastercard';
    case AMEX = 'amex';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::VISA => 'Visa',
            self::MASTERCARD => 'Mastercard',
            self::AMEX => 'American Express',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::VISA => 'info',
            self::MASTERCARD => 'warning',
            self::AMEX => 'success',
        };
    }
}

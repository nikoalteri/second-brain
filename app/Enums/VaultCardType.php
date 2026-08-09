<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VaultCardType: string implements HasLabel, HasColor
{
    case DEBIT = 'debit';
    case PREPAID = 'prepaid';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DEBIT => 'Debit card',
            self::PREPAID => 'Prepaid card',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DEBIT => 'info',
            self::PREPAID => 'warning',
        };
    }
}

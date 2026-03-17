<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanStatus: string implements HasLabel, HasColor
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case DEFAULTED = 'defaulted';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::DEFAULTED => 'Defaulted',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::COMPLETED => 'gray',
            self::DEFAULTED => 'danger',
        };
    }
}

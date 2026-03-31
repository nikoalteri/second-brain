<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InterestCalculationMethod: string implements HasLabel, HasColor
{
    case DAILY_BALANCE = 'daily_balance';
    case DIRECT_MONTHLY = 'direct_monthly';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DAILY_BALANCE => 'Daily Balance Method',
            self::DIRECT_MONTHLY => 'Direct Monthly Method',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DAILY_BALANCE => 'info',
            self::DIRECT_MONTHLY => 'warning',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::DAILY_BALANCE => 'Interest = Σ(daily_balance × rate/365) per each day',
            self::DIRECT_MONTHLY => 'Interest = balance × (annual_rate / 100) applied directly each month',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionFrequency: string implements HasLabel, HasColor
{
    case MONTHLY = 'monthly';
    case ANNUAL = 'annual';
    case BIENNIAL = 'biennial';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MONTHLY => 'Monthly',
            self::ANNUAL => 'Annual',
            self::BIENNIAL => 'Every 2 Years',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MONTHLY => 'info',
            self::ANNUAL => 'warning',
            self::BIENNIAL => 'success',
        };
    }

    /**
     * Get divisor for monthly cost calculation.
     * Used to convert annual_cost to monthly_cost
     */
    public function getMonthlyDivisor(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::ANNUAL => 12,
            self::BIENNIAL => 24,
        };
    }
}

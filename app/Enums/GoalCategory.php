<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum GoalCategory: string implements HasLabel, HasColor
{
    case HEALTH = 'health';
    case CAREER = 'career';
    case FINANCE = 'finance';
    case PERSONAL = 'personal';
    case RELATIONSHIP = 'relationship';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::HEALTH => 'Health',
            self::CAREER => 'Career',
            self::FINANCE => 'Finance',
            self::PERSONAL => 'Personal',
            self::RELATIONSHIP => 'Relationship',
            self::OTHER => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::HEALTH => 'danger',
            self::CAREER => 'info',
            self::FINANCE => 'success',
            self::PERSONAL => 'warning',
            self::RELATIONSHIP => 'danger',
            self::OTHER => 'gray',
        };
    }
}

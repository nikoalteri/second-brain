<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MealType: string implements HasLabel, HasColor
{
    case BREAKFAST = 'breakfast';
    case LUNCH = 'lunch';
    case DINNER = 'dinner';
    case SNACK = 'snack';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BREAKFAST => 'Breakfast 🥐',
            self::LUNCH => 'Lunch 🍲',
            self::DINNER => 'Dinner 🍽️',
            self::SNACK => 'Snack 🍎',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BREAKFAST => 'warning',
            self::LUNCH => 'success',
            self::DINNER => 'info',
            self::SNACK => 'gray',
        };
    }
}

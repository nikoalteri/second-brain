<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HabitDifficulty: string implements HasLabel, HasColor
{
    case EASY = 'easy';
    case MEDIUM = 'medium';
    case HARD = 'hard';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EASY => 'Easy',
            self::MEDIUM => 'Medium',
            self::HARD => 'Hard',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EASY => 'success',
            self::MEDIUM => 'warning',
            self::HARD => 'danger',
        };
    }
}

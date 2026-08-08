<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SavingGoalStatus: string implements HasLabel, HasColor
{
    case ACTIVE = 'active';
    case ACHIEVED = 'achieved';
    case ARCHIVED = 'archived';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::ACHIEVED => 'Achieved',
            self::ARCHIVED => 'Archived',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE => 'primary',
            self::ACHIEVED => 'success',
            self::ARCHIVED => 'gray',
        };
    }
}

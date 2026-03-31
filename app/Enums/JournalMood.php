<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JournalMood: string implements HasLabel, HasColor
{
    case POOR = 'poor';
    case FAIR = 'fair';
    case GOOD = 'good';
    case EXCELLENT = 'excellent';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::POOR => 'Poor 😞',
            self::FAIR => 'Fair 😐',
            self::GOOD => 'Good 🙂',
            self::EXCELLENT => 'Excellent 😄',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::POOR => 'danger',
            self::FAIR => 'warning',
            self::GOOD => 'info',
            self::EXCELLENT => 'success',
        };
    }
}

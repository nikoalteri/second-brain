<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UITheme: string implements HasLabel, HasColor
{
    case LIGHT = 'light';
    case DARK = 'dark';
    case AUTO = 'auto';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LIGHT => 'Light',
            self::DARK => 'Dark',
            self::AUTO => 'Auto (System)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::LIGHT => 'warning',
            self::DARK => 'gray',
            self::AUTO => 'info',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MedicationDosageUnit: string implements HasLabel, HasColor
{
    case MG = 'mg';
    case ML = 'ml';
    case TABLET = 'tablet';
    case CAPSULE = 'capsule';
    case DROPS = 'drops';
    case PATCH = 'patch';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MG => 'Milligrams (mg)',
            self::ML => 'Milliliters (ml)',
            self::TABLET => 'Tablet',
            self::CAPSULE => 'Capsule',
            self::DROPS => 'Drops',
            self::PATCH => 'Patch',
        };
    }

    public function getColor(): string|array|null
    {
        return 'info';
    }
}

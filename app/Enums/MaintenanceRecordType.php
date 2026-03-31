<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MaintenanceRecordType: string implements HasLabel, HasColor
{
    case OIL_CHANGE = 'oil_change';
    case REPAIR = 'repair';
    case INSPECTION = 'inspection';
    case TIRE_SERVICE = 'tire_service';
    case BATTERY_SERVICE = 'battery_service';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OIL_CHANGE => 'Oil Change',
            self::REPAIR => 'Repair',
            self::INSPECTION => 'Inspection',
            self::TIRE_SERVICE => 'Tire Service',
            self::BATTERY_SERVICE => 'Battery Service',
            self::OTHER => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OIL_CHANGE => 'info',
            self::REPAIR => 'danger',
            self::INSPECTION => 'warning',
            self::TIRE_SERVICE => 'gray',
            self::BATTERY_SERVICE => 'info',
            self::OTHER => 'gray',
        };
    }
}

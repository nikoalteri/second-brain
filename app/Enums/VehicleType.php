<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VehicleType: string implements HasLabel, HasColor
{
    case CAR = 'car';
    case MOTORCYCLE = 'motorcycle';
    case BICYCLE = 'bicycle';
    case TRUCK = 'truck';
    case VAN = 'van';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CAR => 'Car 🚗',
            self::MOTORCYCLE => 'Motorcycle 🏍️',
            self::BICYCLE => 'Bicycle 🚴',
            self::TRUCK => 'Truck 🚚',
            self::VAN => 'Van 🚐',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CAR => 'info',
            self::MOTORCYCLE => 'danger',
            self::BICYCLE => 'success',
            self::TRUCK => 'warning',
            self::VAN => 'gray',
        };
    }
}

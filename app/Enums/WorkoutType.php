<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WorkoutType: string implements HasLabel, HasColor
{
    case RUNNING = 'running';
    case CYCLING = 'cycling';
    case SWIMMING = 'swimming';
    case WEIGHT_TRAINING = 'weight_training';
    case YOGA = 'yoga';
    case PILATES = 'pilates';
    case WALKING = 'walking';
    case HIKING = 'hiking';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::RUNNING => 'Running',
            self::CYCLING => 'Cycling',
            self::SWIMMING => 'Swimming',
            self::WEIGHT_TRAINING => 'Weight Training',
            self::YOGA => 'Yoga',
            self::PILATES => 'Pilates',
            self::WALKING => 'Walking',
            self::HIKING => 'Hiking',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::RUNNING => 'danger',
            self::CYCLING => 'info',
            self::SWIMMING => 'success',
            self::WEIGHT_TRAINING => 'warning',
            self::YOGA => 'gray',
            self::PILATES => 'success',
            self::WALKING => 'gray',
            self::HIKING => 'warning',
        };
    }
}

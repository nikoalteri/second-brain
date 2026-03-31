<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum HealthRecordType: string implements HasLabel, HasColor
{
    case BLOOD_PRESSURE = 'blood_pressure';
    case HEART_RATE = 'heart_rate';
    case WEIGHT = 'weight';
    case HEIGHT = 'height';
    case BMI = 'bmi';
    case TEMPERATURE = 'temperature';
    case BLOOD_SUGAR = 'blood_sugar';
    case CHOLESTEROL = 'cholesterol';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BLOOD_PRESSURE => 'Blood Pressure',
            self::HEART_RATE => 'Heart Rate',
            self::WEIGHT => 'Weight',
            self::HEIGHT => 'Height',
            self::BMI => 'BMI',
            self::TEMPERATURE => 'Temperature',
            self::BLOOD_SUGAR => 'Blood Sugar',
            self::CHOLESTEROL => 'Cholesterol',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BLOOD_PRESSURE => 'danger',
            self::HEART_RATE => 'danger',
            self::WEIGHT => 'warning',
            self::HEIGHT => 'gray',
            self::BMI => 'warning',
            self::TEMPERATURE => 'danger',
            self::BLOOD_SUGAR => 'danger',
            self::CHOLESTEROL => 'danger',
        };
    }
}

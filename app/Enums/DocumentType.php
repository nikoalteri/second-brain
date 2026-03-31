<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel, HasColor
{
    case RECEIPT = 'receipt';
    case INVOICE = 'invoice';
    case REPORT = 'report';
    case CERTIFICATE = 'certificate';
    case INSURANCE = 'insurance';
    case OTHER = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::RECEIPT => 'Receipt 🧾',
            self::INVOICE => 'Invoice 📄',
            self::REPORT => 'Report 📊',
            self::CERTIFICATE => 'Certificate 🏆',
            self::INSURANCE => 'Insurance 🛡️',
            self::OTHER => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::RECEIPT => 'info',
            self::INVOICE => 'warning',
            self::REPORT => 'gray',
            self::CERTIFICATE => 'success',
            self::INSURANCE => 'danger',
            self::OTHER => 'gray',
        };
    }
}

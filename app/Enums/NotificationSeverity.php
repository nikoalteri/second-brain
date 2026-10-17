<?php

namespace App\Enums;

enum NotificationSeverity: string
{
    case WARNING = 'warning';
    case EXCEEDED = 'exceeded';
    case CRITICAL = 'critical';

    public function rank(): int
    {
        return match ($this) {
            self::WARNING => 1,
            self::EXCEEDED => 2,
            self::CRITICAL => 3,
        };
    }

    public function isHigherThan(?self $other): bool
    {
        return $other === null || $this->rank() > $other->rank();
    }
}

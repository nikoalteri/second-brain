<?php

namespace App\Services\Notifications;

use App\Enums\NotificationSeverity;
use InvalidArgumentException;

/**
 * A condition that is currently true for one owner, as evaluated by a trigger.
 */
final class ConditionSpec
{
    public function __construct(
        public readonly string $trigger,
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $periodKey,
        public readonly NotificationSeverity $severity,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $actionUrl = null,
    ) {
        if ($periodKey === '') {
            throw new InvalidArgumentException('periodKey must not be empty; use a constant for open-ended conditions.');
        }

        if ($actionUrl !== null && ! self::isInternalPath($actionUrl)) {
            throw new InvalidArgumentException('actionUrl must be an application-relative path.');
        }
    }

    public function identity(): string
    {
        return implode('|', [$this->trigger, $this->subjectType, $this->subjectId, $this->periodKey]);
    }

    private static function isInternalPath(string $url): bool
    {
        return str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
            && ! str_contains($url, '\\')
            && ! preg_match('/[\x00-\x1F]/', $url);
    }
}

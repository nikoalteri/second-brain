<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Daily digest: evaluate each owner's conditions, publish through the condition
 * lifecycle, and purge long-read notifications.
 */
class FinanceNotificationDigest
{
    public const READ_RETENTION_DAYS = 90;

    public function __construct(
        private readonly FinanceNotificationEvaluator $evaluator,
        private readonly NotificationConditionPublisher $publisher,
    ) {}

    /**
     * Evaluate first and reconcile only on success, so a failed evaluation never
     * resolves any of the owner's conditions.
     *
     * @return array{created: int, resolved: int}
     */
    public function runForUser(int $userId, CarbonInterface $date): array
    {
        // Snapshot before evaluating: a concurrent run that publishes a condition while
        // this evaluation is in flight must not have it resolved from stale data.
        $snapshot = $this->publisher->snapshot($userId, FinanceNotificationEvaluator::TRIGGERS);
        $specs = $this->evaluator->evaluate($userId, $date);

        return $this->publisher->reconcile($userId, FinanceNotificationEvaluator::TRIGGERS, $specs, $snapshot);
    }

    /**
     * @param  callable(int, Throwable): void|null  $onFailure
     * @return array{users: int, created: int, resolved: int, failed: int}
     */
    public function run(CarbonInterface $date, ?callable $onFailure = null): array
    {
        $totals = ['users' => 0, 'created' => 0, 'resolved' => 0, 'failed' => 0];

        User::query()->where('is_active', true)->orderBy('id')->pluck('id')->each(
            function (int $userId) use ($date, $onFailure, &$totals) {
                $totals['users']++;

                try {
                    $result = $this->runForUser($userId, $date);
                    $totals['created'] += $result['created'];
                    $totals['resolved'] += $result['resolved'];
                } catch (Throwable $e) {
                    $totals['failed']++;
                    $onFailure?->__invoke($userId, $e);
                }
            }
        );

        return $totals;
    }

    /**
     * Permanently delete notifications read more than the retention window ago.
     * Unread notifications and condition rows are kept.
     */
    public function purgeReadNotifications(CarbonInterface $date): int
    {
        $cutoff = $date->copy()->subDays(self::READ_RETENTION_DAYS);
        $deleted = 0;

        Notification::query()
            ->withoutUserScope()
            ->withTrashed()
            ->whereNotNull('read_at')
            ->where('read_at', '<', $cutoff)
            ->chunkById(500, function ($notifications) use (&$deleted) {
                foreach ($notifications as $notification) {
                    $notification->forceDelete();
                    $deleted++;
                }
            });

        return $deleted;
    }
}

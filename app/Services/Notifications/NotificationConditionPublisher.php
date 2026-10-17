<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\NotificationCondition;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Turns evaluated conditions into at most one notification per condition episode
 * and severity level. Reading or deleting a notification never touches condition
 * state; only the evaluator's results do. Trigger-agnostic so later phases reuse it.
 */
class NotificationConditionPublisher
{
    /**
     * Publish one currently-true condition. Returns the notification created, if any.
     */
    public function publish(int $userId, ConditionSpec $spec): ?Notification
    {
        try {
            return $this->publishOnce($userId, $spec);
        } catch (UniqueConstraintViolationException) {
            // A concurrent run created the condition or notification first; its row
            // is now committed, so one retry observes it and creates nothing extra.
            return $this->publishOnce($userId, $spec);
        }
    }

    /**
     * Record which conditions are active, and at which publish version, before an
     * evaluation starts. Pass the result to reconcile() so a condition that another
     * run published while this evaluation was in flight is not wrongly resolved.
     *
     * @param  list<string>  $triggers
     * @return array<int, int> condition id => publish_seq
     */
    public function snapshot(int $userId, array $triggers): array
    {
        return NotificationCondition::query()
            ->where('user_id', $userId)
            ->whereIn('trigger', $triggers)
            ->where('active', true)
            ->pluck('publish_seq', 'id')
            ->map(fn ($seq) => (int) $seq)
            ->all();
    }

    /**
     * Reconcile a fully evaluated set for the given triggers. Call only after the
     * owner's evaluation completed successfully: conditions of $triggers that were
     * active in $snapshot, are missing from $evaluated and were not published again
     * since the snapshot are marked resolved. Without a snapshot, one is taken now
     * (which cannot detect a publish that happened during evaluation).
     *
     * @param  list<string>  $triggers
     * @param  iterable<ConditionSpec>  $evaluated
     * @param  array<int, int>|null  $snapshot
     * @return array{created: int, resolved: int}
     */
    public function reconcile(int $userId, array $triggers, iterable $evaluated, ?array $snapshot = null): array
    {
        $snapshot ??= $this->snapshot($userId, $triggers);
        $created = 0;
        $seen = [];

        foreach ($evaluated as $spec) {
            if (! in_array($spec->trigger, $triggers, true)) {
                continue;
            }

            $seen[$spec->identity()] = true;

            if ($this->publish($userId, $spec) !== null) {
                $created++;
            }
        }

        $resolved = 0;

        NotificationCondition::query()
            ->where('user_id', $userId)
            ->whereIn('id', array_keys($snapshot))
            ->get()
            ->each(function (NotificationCondition $condition) use ($seen, $snapshot, &$resolved) {
                $identity = implode('|', [
                    $condition->trigger, $condition->subject_type, $condition->subject_id, $condition->period_key,
                ]);

                if (isset($seen[$identity])) {
                    return;
                }

                DB::transaction(function () use ($condition, $snapshot, &$resolved) {
                    $locked = NotificationCondition::query()->lockForUpdate()->find($condition->id);

                    // Resolve only if the condition is still active and nobody published it
                    // since the snapshot: otherwise this evaluation is stale for it.
                    if ($locked?->active && $locked->publish_seq === $snapshot[$condition->id]) {
                        $locked->update(['active' => false, 'resolved_at' => now()]);
                        $resolved++;
                    }
                });
            });

        return ['created' => $created, 'resolved' => $resolved];
    }

    private function publishOnce(int $userId, ConditionSpec $spec): ?Notification
    {
        return DB::transaction(function () use ($userId, $spec) {
            $identity = [
                'user_id' => $userId,
                'trigger' => $spec->trigger,
                'subject_type' => $spec->subjectType,
                'subject_id' => $spec->subjectId,
                'period_key' => $spec->periodKey,
            ];

            $condition = NotificationCondition::query()->where($identity)->lockForUpdate()->first();

            if ($condition === null) {
                $condition = NotificationCondition::create($identity + [
                    'active' => true,
                    'episode' => 1,
                    'publish_seq' => 1,
                    'current_severity' => $spec->severity,
                    'activated_at' => now(),
                ]);

                return $this->notify($condition, $spec);
            }

            if (! $condition->active) {
                $condition->update([
                    'active' => true,
                    'episode' => $condition->episode + 1,
                    'publish_seq' => $condition->publish_seq + 1,
                    'current_severity' => $spec->severity,
                    'max_notified_severity' => null,
                    'activated_at' => now(),
                    'resolved_at' => null,
                ]);

                return $this->notify($condition, $spec);
            }

            $condition->current_severity = $spec->severity;
            $condition->publish_seq++;

            if ($spec->severity->isHigherThan($condition->max_notified_severity)) {
                $notification = $this->notify($condition, $spec);
                $condition->save();

                return $notification;
            }

            $condition->save();

            return null;
        });
    }

    private function notify(NotificationCondition $condition, ConditionSpec $spec): Notification
    {
        $notification = Notification::query()->withoutUserScope()->create([
            'user_id' => $condition->user_id,
            'type' => 'in_app',
            'title' => $spec->title,
            'message' => $spec->message,
            'action_url' => $spec->actionUrl,
            'related_model' => $spec->subjectType,
            'related_id' => $spec->subjectId,
            'notification_condition_id' => $condition->id,
            'episode' => $condition->episode,
            'severity' => $spec->severity->value,
        ]);

        $condition->update(['max_notified_severity' => $spec->severity]);

        return $notification;
    }
}

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
     * Reconcile a fully evaluated set for the given triggers. Call only after the
     * owner's evaluation completed successfully: active conditions of $triggers
     * missing from $evaluated are marked resolved.
     *
     * @param  list<string>  $triggers
     * @param  iterable<ConditionSpec>  $evaluated
     * @return array{created: int, resolved: int}
     */
    public function reconcile(int $userId, array $triggers, iterable $evaluated): array
    {
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
            ->whereIn('trigger', $triggers)
            ->where('active', true)
            ->get()
            ->each(function (NotificationCondition $condition) use ($seen, &$resolved) {
                $identity = implode('|', [
                    $condition->trigger, $condition->subject_type, $condition->subject_id, $condition->period_key,
                ]);

                if (isset($seen[$identity])) {
                    return;
                }

                // Re-check under lock so a concurrent publish is not overwritten.
                DB::transaction(function () use ($condition, &$resolved) {
                    $locked = NotificationCondition::query()->lockForUpdate()->find($condition->id);

                    if ($locked?->active) {
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
                    'current_severity' => $spec->severity,
                    'activated_at' => now(),
                ]);

                return $this->notify($condition, $spec);
            }

            if (! $condition->active) {
                $condition->update([
                    'active' => true,
                    'episode' => $condition->episode + 1,
                    'current_severity' => $spec->severity,
                    'max_notified_severity' => null,
                    'activated_at' => now(),
                    'resolved_at' => null,
                ]);

                return $this->notify($condition, $spec);
            }

            $condition->current_severity = $spec->severity;

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

<?php

namespace App\Observers;

use App\Jobs\SendMaintenanceReminder;
use App\Models\MaintenanceTask;

class MaintenanceTaskObserver
{
    public function created(MaintenanceTask $task): void
    {
        if ($task->next_due_date) {
            // Queue reminder for 7 days before due date
            $dispatchTime = $task->next_due_date->clone()->subDays(7);

            // Only queue if dispatch time is in the future
            if ($dispatchTime->isFuture()) {
                SendMaintenanceReminder::dispatch($task, $task->user)
                    ->delay($dispatchTime);
            }
        }
    }

    public function updated(MaintenanceTask $task): void
    {
        // If next_due_date was changed, reschedule reminder
        if ($task->isDirty('next_due_date') && $task->next_due_date) {
            $dispatchTime = $task->next_due_date->clone()->subDays(7);

            if ($dispatchTime->isFuture()) {
                SendMaintenanceReminder::dispatch($task, $task->user)
                    ->delay($dispatchTime);
            }
        }
    }

    public function restored(MaintenanceTask $task): void
    {
        // If soft-deleted task restored, requeue reminder
        if ($task->next_due_date) {
            $dispatchTime = $task->next_due_date->clone()->subDays(7);

            if ($dispatchTime->isFuture()) {
                SendMaintenanceReminder::dispatch($task, $task->user)
                    ->delay($dispatchTime);
            }
        }
    }
}

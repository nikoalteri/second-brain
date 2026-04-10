<?php

namespace App\Jobs;

use App\Models\MaintenanceTask;
use App\Models\User;
use App\Notifications\MaintenanceReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMaintenanceReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public MaintenanceTask $task,
        public User $user
    ) {}

    public function handle(): void
    {
        try {
            // Reload task to ensure fresh data
            $task = MaintenanceTask::findOrFail($this->task->id);

            // Verify user still has access
            if ($task->user_id !== $this->user->id) {
                Log::warning('Maintenance reminder unauthorized access attempt', [
                    'task_id' => $task->id,
                    'user_id' => $this->user->id,
                ]);

                return;
            }

            // Send notification
            $this->user->notify(new MaintenanceReminderNotification($task));

            Log::info('Maintenance reminder sent', [
                'task_id' => $task->id,
                'user_id' => $this->user->id,
                'property_id' => $task->property_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send maintenance reminder', [
                'task_id' => $this->task->id,
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

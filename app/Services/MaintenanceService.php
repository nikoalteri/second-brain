<?php

namespace App\Services;

use App\Jobs\SendMaintenanceReminder;
use App\Models\MaintenanceTask;
use App\Models\Property;
use App\Models\PropertyMaintenanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

class MaintenanceService
{
    public function __construct(private LoggerInterface $logger) {}

    public function defineTask(Property $property, array $data): MaintenanceTask
    {
        $task = new MaintenanceTask($data);
        $task->property_id = $property->id;
        $task->user_id = $property->user_id;

        // Calculate next_due_date based on frequency
        $task->next_due_date = $this->calculateNextDueDate(
            now(),
            $data['frequency'] ?? 'as_needed'
        );

        $task->save();

        $this->logger->info("Maintenance task created", [
            'task_id' => $task->id,
            'property_id' => $property->id,
            'frequency' => $data['frequency'] ?? null,
        ]);

        return $task;
    }

    public function logRecord(MaintenanceTask $task, array $data): PropertyMaintenanceRecord
    {
        $record = new PropertyMaintenanceRecord($data);
        $record->maintenance_task_id = $task->id;
        $record->user_id = $task->user_id;
        $record->save();

        // Update parent task
        $task->last_completed_date = $data['date'];
        $task->next_due_date = $this->calculateNextDueDate(
            Carbon::parse($data['date']),
            $task->frequency
        );
        $task->save();

        $this->logger->info("Maintenance record logged", [
            'record_id' => $record->id,
            'task_id' => $task->id,
            'cost' => $data['cost'] ?? null,
        ]);

        return $record;
    }

    public function sendReminders(): int
    {
        $overdueTasks = MaintenanceTask::where('next_due_date', '<=', now())
            ->where('status', 'active')
            ->get();

        $upcomingTasks = MaintenanceTask::whereBetween('next_due_date', [
            now(),
            now()->addDays(7)
        ])
            ->where('status', 'active')
            ->get();

        $allTasks = $overdueTasks->merge($upcomingTasks)->unique('id');
        $count = 0;

        foreach ($allTasks as $task) {
            SendMaintenanceReminder::dispatch($task, $task->user);
            $count++;
        }

        $this->logger->info("Maintenance reminders sent", [
            'count' => $count,
        ]);

        return $count;
    }

    public function getTasksForProperty(Property $property): Collection
    {
        return $property->maintenanceTasks()
            ->orderBy('next_due_date')
            ->get();
    }

    public function getSchedule(Property $property, int $months = 12): array
    {
        $tasks = $property->maintenanceTasks()
            ->where('next_due_date', '<=', now()->addMonths($months))
            ->where('next_due_date', '>=', now())
            ->orderBy('next_due_date')
            ->get();

        $schedule = [];

        foreach ($tasks as $task) {
            if (!$task->next_due_date) {
                continue;
            }

            $monthKey = $task->next_due_date->format('Y-m');
            $monthName = $task->next_due_date->format('F Y');

            if (!isset($schedule[$monthKey])) {
                $schedule[$monthKey] = [
                    'month' => $monthName,
                    'tasks' => [],
                ];
            }

            $schedule[$monthKey]['tasks'][] = $task;
        }

        return array_values($schedule);
    }

    private function calculateNextDueDate(Carbon $fromDate, string $frequency): ?Carbon
    {
        return match ($frequency) {
            'weekly' => $fromDate->clone()->addWeeks(1),
            'monthly' => $fromDate->clone()->addMonths(1),
            'quarterly' => $fromDate->clone()->addMonths(3),
            'annually' => $fromDate->clone()->addYear(),
            'as_needed' => null,
            default => null,
        };
    }
}

<?php

namespace Tests\Unit\Services;

use App\Jobs\SendMaintenanceReminder;
use App\Models\MaintenanceTask;
use App\Models\Property;
use App\Models\User;
use App\Services\MaintenanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceService $service;
    private User $user;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MaintenanceService::class);
        $this->user = User::factory()->create();
        $this->property = Property::factory()->for($this->user)->create();
        Bus::fake();
    }

    #[Test]
    public function defines_task_with_next_due_date_calculated()
    {
        $data = [
            'name' => 'HVAC Service',
            'type' => 'preventive',
            'frequency' => 'annually',
            'description' => 'Annual service',
        ];

        $task = $this->service->defineTask($this->property, $data);

        $this->assertNotNull($task->next_due_date);
        $this->assertTrue($task->next_due_date->isFuture());
        $this->assertGreaterThanOrEqual(364, $task->next_due_date->diffInDays(now()));
    }

    #[Test]
    public function calculates_next_due_date_by_frequency()
    {
        // Weekly
        $weekly = $this->service->defineTask($this->property, [
            'name' => 'Weekly Check',
            'type' => 'inspection',
            'frequency' => 'weekly',
        ]);
        $this->assertGreaterThanOrEqual(6, $weekly->next_due_date->diffInDays(now()));

        // Monthly
        $monthly = $this->service->defineTask($this->property, [
            'name' => 'Filter Change',
            'type' => 'maintenance',
            'frequency' => 'monthly',
        ]);
        $this->assertGreaterThanOrEqual(28, $monthly->next_due_date->diffInDays(now()));

        // Quarterly
        $quarterly = $this->service->defineTask($this->property, [
            'name' => 'Inspection',
            'type' => 'inspection',
            'frequency' => 'quarterly',
        ]);
        $this->assertGreaterThanOrEqual(89, $quarterly->next_due_date->diffInDays(now()));
    }

    #[Test]
    public function as_needed_frequency_sets_null_due_date()
    {
        $task = $this->service->defineTask($this->property, [
            'name' => 'As Needed Task',
            'type' => 'repair',
            'frequency' => 'as_needed',
        ]);

        $this->assertNull($task->next_due_date);
    }

    #[Test]
    public function logs_record_and_updates_task_dates()
    {
        $task = MaintenanceTask::factory()
            ->for($this->property)
            ->for($this->user)
            ->create([
                'frequency' => 'monthly',
                'last_completed_date' => null,
            ]);

        $record = $this->service->logRecord($task, [
            'date' => '2024-01-15',
            'cost' => 250,
            'contractor' => 'HVAC Co',
        ]);

        $task->refresh();
        $this->assertEquals('2024-01-15', $task->last_completed_date->format('Y-m-d'));
        $this->assertTrue($task->next_due_date->isAfter('2024-01-15'));
    }

    #[Test]
    public function sends_reminders_for_tasks_due_soon()
    {
        MaintenanceTask::factory()
            ->for($this->property)
            ->for($this->user)
            ->create([
                'next_due_date' => now()->addDays(5),
            ]);

        $count = $this->service->sendReminders();

        $this->assertGreater($count, 0);
        Bus::assertDispatched(SendMaintenanceReminder::class);
    }

    #[Test]
    public function does_not_send_reminders_for_tasks_far_in_future()
    {
        MaintenanceTask::factory()
            ->for($this->property)
            ->for($this->user)
            ->create([
                'next_due_date' => now()->addMonths(3),
            ]);

        $count = $this->service->sendReminders();

        $this->assertEquals(0, $count);
        Bus::assertNotDispatched(SendMaintenanceReminder::class);
    }

    #[Test]
    public function sends_reminders_for_overdue_tasks()
    {
        MaintenanceTask::factory()
            ->for($this->property)
            ->for($this->user)
            ->create([
                'next_due_date' => now()->subDays(5),
            ]);

        $count = $this->service->sendReminders();

        $this->assertGreater($count, 0);
    }

    #[Test]
    public function gets_tasks_for_property_ordered_by_due_date()
    {
        $task1 = MaintenanceTask::factory()
            ->for($this->property)
            ->for($this->user)
            ->create(['next_due_date' => now()->addDays(10)]);

        $task2 = MaintenanceTask::factory()
            ->for($this->property)
            ->for($this->user)
            ->create(['next_due_date' => now()->addDays(5)]);

        $tasks = $this->service->getTasksForProperty($this->property);

        $this->assertEquals($task2->id, $tasks->first()->id);
        $this->assertEquals($task1->id, $tasks->last()->id);
    }

    #[Test]
    public function gets_schedule_grouped_by_month()
    {
        MaintenanceTask::factory()
            ->for($this->property)
            ->for($this->user)
            ->create(['next_due_date' => now()->addDays(5)]);

        MaintenanceTask::factory()
            ->for($this->property)
            ->for($this->user)
            ->create(['next_due_date' => now()->addMonths(1)->addDays(5)]);

        $schedule = $this->service->getSchedule($this->property, 12);

        $this->assertGreater(count($schedule), 0);
        $this->assertArrayHasKey('month', $schedule[0]);
        $this->assertArrayHasKey('tasks', $schedule[0]);
    }
}

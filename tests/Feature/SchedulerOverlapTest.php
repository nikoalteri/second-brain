<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerOverlapTest extends TestCase
{
    public function test_the_scheduled_jobs_do_not_overlap(): void
    {
        $events = collect(app(Schedule::class)->events());

        foreach (['loans:sync-installments', 'subscriptions:sync-renewals', 'credit-cards:generate-cycles'] as $command) {
            $event = $events->first(fn ($event) => str_contains((string) $event->command, $command));

            $this->assertNotNull($event, "{$command} is not scheduled");
            $this->assertTrue($event->withoutOverlapping, "{$command} may overlap itself");
        }
    }
}

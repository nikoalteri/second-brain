<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SanctumPruneScheduleTest extends TestCase
{
    public function test_expired_and_consumed_tokens_are_pruned_daily(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");

        $this->assertStringContainsString('sanctum:prune-expired --hours=168', $commands);
    }
}

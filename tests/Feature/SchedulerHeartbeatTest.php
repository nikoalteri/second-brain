<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SchedulerHeartbeatTest extends TestCase
{
    public function test_a_scheduler_run_records_a_heartbeat(): void
    {
        $this->assertNull(Cache::get('scheduler:heartbeat'));

        Artisan::call('schedule:run');

        $this->assertNotNull(Cache::get('scheduler:heartbeat'));
    }

    public function test_the_status_is_ok_right_after_a_run(): void
    {
        Artisan::call('schedule:run');

        $this->getJson('/health/scheduler')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_the_status_is_stale_when_the_scheduler_never_ran(): void
    {
        $this->getJson('/health/scheduler')
            ->assertStatus(503)
            ->assertJsonPath('status', 'stale')
            ->assertJsonPath('age_seconds', null);
    }

    public function test_the_status_turns_stale_when_the_heartbeat_is_too_old(): void
    {
        Cache::forever('scheduler:heartbeat', Carbon::now()->subMinutes(10)->timestamp);

        $this->getJson('/health/scheduler')
            ->assertStatus(503)
            ->assertJsonPath('status', 'stale');
    }

    public function test_a_recent_heartbeat_within_the_tolerance_is_ok(): void
    {
        Cache::forever('scheduler:heartbeat', Carbon::now()->subMinutes(3)->timestamp);

        $this->getJson('/health/scheduler')->assertOk();
    }

    public function test_the_status_reveals_nothing_beyond_state_and_age(): void
    {
        Artisan::call('schedule:run');

        $this->assertEqualsCanonicalizing(['status', 'age_seconds'], array_keys($this->getJson('/health/scheduler')->json()));
    }
}

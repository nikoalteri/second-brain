<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmokeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_healthy_application_passes(): void
    {
        $this->artisan('app:smoke')
            ->expectsOutputToContain('database')
            ->expectsOutputToContain('migrations')
            ->expectsOutputToContain('Smoke test passed.')
            ->assertSuccessful();
    }

    public function test_pending_migrations_fail_the_smoke_test(): void
    {
        DB::table('migrations')->orderByDesc('id')->limit(1)->delete();

        $this->artisan('app:smoke')
            ->expectsOutputToContain('pending migration')
            ->assertFailed();
    }

    public function test_an_unreachable_database_fails_the_smoke_test(): void
    {
        $original = config('database.default');
        config(['database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent-dir/none.sqlite', 'prefix' => '']]);

        try {
            config(['database.default' => 'broken']);
            DB::purge('broken');

            $this->artisan('app:smoke')
                ->expectsOutputToContain('FAIL')
                ->assertFailed();
        } finally {
            // Put the working connection back so the test's own teardown can clean up.
            config(['database.default' => $original]);
            DB::purge('broken');
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Throwable;

/**
 * Quick end-of-deploy check that the application actually works, so a deploy that "succeeded"
 * but left a broken app is caught before the pre-deploy backup is thrown away.
 */
class SmokeTest extends Command
{
    protected $signature = 'app:smoke';

    protected $description = 'Check that the database, migrations, cache, storage and GraphQL schema are healthy (for the end of a deploy)';

    public function handle(): int
    {
        $failures = 0;

        foreach ($this->checks() as $name => $check) {
            try {
                $detail = $check();
                $this->line("<info>OK</info>   {$name}".($detail ? " - {$detail}" : ''));
            } catch (Throwable $exception) {
                $failures++;
                $this->line("<error>FAIL</error> {$name} - ".$exception->getMessage());
            }
        }

        if ($failures > 0) {
            $this->newLine();
            $this->error("Smoke test failed ({$failures} check(s)).");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Smoke test passed.');

        return self::SUCCESS;
    }

    /** @return array<string, callable(): ?string> */
    private function checks(): array
    {
        return [
            'database' => function () {
                DB::select('select 1');

                return null;
            },
            'migrations' => function () {
                $migrator = app('migrator');
                $files = array_keys($migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')])));
                $pending = array_diff($files, $migrator->getRepository()->getRan());

                if ($pending !== []) {
                    throw new \RuntimeException(count($pending).' pending migration(s), first: '.reset($pending));
                }

                return null;
            },
            'cache' => function () {
                $key = 'smoke:'.Str::random(8);
                Cache::put($key, 'ok', 30);

                if (Cache::pull($key) !== 'ok') {
                    throw new \RuntimeException('a value written to the cache could not be read back');
                }

                return null;
            },
            'storage' => function () {
                foreach ([storage_path('logs'), storage_path('framework/cache'), base_path('bootstrap/cache')] as $directory) {
                    if (! is_writable($directory)) {
                        throw new \RuntimeException("{$directory} is not writable");
                    }
                }

                return null;
            },
            'graphql schema' => function () {
                app(SchemaBuilder::class)->schema();

                return null;
            },
        ];
    }
}

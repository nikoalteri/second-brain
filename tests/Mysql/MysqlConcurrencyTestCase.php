<?php

declare(strict_types=1);

namespace Tests\Mysql;

use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\Import\ImportBatchService;
use App\Services\Import\ImportMappingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real two-connection tests. The parent process builds committed data on MySQL, then drives worker
 * processes (tests/Mysql/worker.php) that each hold their own connection and freeze at a chosen point,
 * so the interleaving is deterministic: "writer A holds its lock and is frozen; writer B starts and
 * must wait; A is released; B continues". Nothing here runs unless the default connection is MySQL
 * (phpunit.mysql.xml); see tests/Mysql/README.md.
 */
abstract class MysqlConcurrencyTestCase extends TestCase
{
    private static bool $migrated = false;

    /** @var list<Process> */
    private array $processes = [];

    private string $dir;

    protected function setUp(): void
    {
        // Children inherit this environment; a synthetic key is enough for tests.
        if (! getenv('APP_KEY')) {
            $key = 'base64:' . base64_encode(random_bytes(32));
            putenv("APP_KEY={$key}");
            $_ENV['APP_KEY'] = $key;
        }

        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Run with phpunit.mysql.xml against a MySQL server (see tests/Mysql/README.md).');
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL is not reachable: ' . $e->getMessage());
        }

        if (! self::$migrated) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            self::$migrated = true;
        }

        $this->truncateEverything();
        $this->dir = sys_get_temp_dir() . '/fluxa-mysql-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }

        if (isset($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            @rmdir($this->dir);
        }

        parent::tearDown();
    }

    private function truncateEverything(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (DB::select('SHOW TABLES') as $row) {
            $table = (string) array_values((array) $row)[0];

            if ($table !== 'migrations') {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    // ─── workers ────────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $spec action + params; `pause_on` is 'creating' or 'end'
     * @return array<string, mixed> the spec plus its sync files
     */
    protected function spec(array $spec): array
    {
        $id = bin2hex(random_bytes(3));

        return $spec + ['ready' => "{$this->dir}/{$id}.ready", 'release' => "{$this->dir}/{$id}.release"];
    }

    /** @param array<string, mixed> $spec */
    protected function start(array $spec): Process
    {
        $process = new Process([PHP_BINARY, '-d', 'memory_limit=-1', __DIR__ . '/worker.php', json_encode($spec, JSON_THROW_ON_ERROR)]);
        $process->setTimeout(90);
        $process->start();

        return $this->processes[] = $process;
    }

    /** Waits until the worker froze at its pause point. */
    protected function awaitFrozen(array $spec, Process $process, float $seconds = 30): void
    {
        $deadline = microtime(true) + $seconds;

        while (! file_exists($spec['ready'])) {
            if (! $process->isRunning()) {
                $this->fail('The worker ended before it froze: ' . $process->getOutput() . $process->getErrorOutput());
            }

            if (microtime(true) > $deadline) {
                $this->fail('The worker never reached its pause point.');
            }

            usleep(20_000);
        }
    }

    /** The second writer must be WAITING on the first one's lock: still running, never reached its pause. */
    protected function assertWaiting(Process $process, ?array $spec = null, int $milliseconds = 1500): void
    {
        usleep($milliseconds * 1000);

        $this->assertTrue($process->isRunning(), 'The second writer should be blocked on the lock, but it already ended: ' . $process->getOutput() . $process->getErrorOutput());

        if ($spec !== null) {
            $this->assertFileDoesNotExist($spec['ready'], 'The second writer got past the lock while the first still held it.');
        }
    }

    protected function release(array $spec): void
    {
        touch($spec['release']);
    }

    /** @return array<string, mixed> the worker's JSON result; fails on any error (deadlock, timeout …) */
    protected function outcomeOf(Process $process): array
    {
        $process->wait();
        $line = trim(explode(PHP_EOL, trim($process->getOutput()))[0] ?? '');
        $decoded = json_decode($line, true);

        $this->assertTrue($process->isSuccessful(), 'Worker failed: ' . $process->getOutput() . $process->getErrorOutput());
        $this->assertIsArray($decoded, 'Worker produced no result: ' . $process->getOutput());

        return $decoded;
    }

    // ─── data ───────────────────────────────────────────────────────────────────────────────

    protected function user(): User
    {
        return User::factory()->create();
    }

    protected function account(User $user, float $balance = 1000.0): Account
    {
        return Account::factory()->create(['user_id' => $user->id, 'currency' => 'EUR', 'balance' => $balance, 'is_active' => true]);
    }

    /** A mapped batch (committed) over a Date,Amount,Description CSV; rows are numbered from 2. */
    protected function mappedBatch(User $user, Account $account, string $csv): ImportBatch
    {
        $batch = app(ImportBatchService::class)->stage($user, UploadedFile::fake()->createWithContent('s.csv', $csv), ['account_id' => $account->id]);

        app(ImportMappingService::class)->apply($batch, [
            'header_row' => 1, 'date_column' => 0, 'date_format' => 'Y-m-d', 'amount_mode' => 'single', 'amount_column' => 1,
            'debit_column' => null, 'credit_column' => null, 'decimal_separator' => '.', 'thousands_separator' => ',',
            'invert_sign' => false, 'description_column' => 2, 'category_column' => null, 'currency_column' => null,
        ]);

        return $batch->fresh();
    }

    protected function row(ImportBatch $batch, int $number): ImportRow
    {
        return ImportRow::where('import_batch_id', $batch->id)->where('row_number', $number)->firstOrFail();
    }

    protected function ledgerCount(Account $account): int
    {
        return DB::table('transactions')->where('account_id', $account->id)->whereNull('deleted_at')->count();
    }

    protected function balance(Account $account): float
    {
        return (float) DB::table('accounts')->where('id', $account->id)->value('balance');
    }
}

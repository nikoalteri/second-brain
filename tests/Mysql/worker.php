<?php

/**
 * Second database connection for tests/Mysql: a separate PHP process that runs ONE ledger action and
 * can freeze itself at a chosen point so the parent test can interleave another writer deterministically.
 *
 * Usage: php worker.php '<json spec>'   (see MysqlConcurrencyTestCase::start())
 * Output: one JSON line with the action's result, or {"error": "..."}.
 */

use App\Models\CreditCardExpense;
use App\Models\ImportBatch;
use App\Models\LoanPayment;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\AccountTransferService;
use App\Services\Import\ImportRowPromotionService;
use App\Services\LoanPaymentPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$spec = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);

function wait_for(?string $file, int $seconds = 45): void
{
    $deadline = microtime(true) + $seconds;

    while ($file !== null && ! file_exists($file)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException("Timed out waiting for {$file}");
        }

        usleep(20_000);
    }
}

// Tell the parent "I hold what I hold", then stay frozen until it says go.
$pause = function () use ($spec): void {
    touch($spec['ready']);
    wait_for($spec['release']);
};

try {
    Auth::setUser(User::findOrFail($spec['user_id']));
    wait_for($spec['start'] ?? null);

    if (($spec['pause_on'] ?? null) === 'creating') {
        // Registered after the observer, so it fires once the target lock is held and (for the import)
        // after the duplicate re-check, right before the insert.
        Transaction::creating($pause);
        CreditCardExpense::creating($pause);
    }

    $result = match ($spec['action']) {
        'manual_create' => (function () use ($spec, $pause) {
            // What TransactionController::store does: one transaction around the model create.
            return DB::transaction(function () use ($spec, $pause) {
                $type = TransactionType::query()->firstOrCreate(
                    ['name' => $spec['amount'] > 0 ? 'Income' : 'Expense'],
                    ['is_income' => $spec['amount'] > 0],
                );
                $transaction = Transaction::create([
                    'user_id' => $spec['user_id'], 'account_id' => $spec['account_id'], 'transaction_type_id' => $type->id,
                    'amount' => $spec['amount'], 'date' => $spec['date'], 'description' => $spec['description'],
                ]);

                if (($spec['pause_on'] ?? null) === 'end') {
                    $pause(); // inserted, lock held, not yet committed
                }

                return ['id' => $transaction->id];
            });
        })(),
        'import_promote' => app(ImportRowPromotionService::class)->promote(ImportBatch::findOrFail($spec['batch_id']), $spec['row_id']),
        'import_confirm' => app(ImportRowPromotionService::class)->confirm(ImportBatch::findOrFail($spec['batch_id']), $spec['row_ids'] ?? null),
        'loan_posting' => (function () use ($spec) {
            app(LoanPaymentPostingService::class)->syncPosting(LoanPayment::findOrFail($spec['loan_payment_id']));

            return ['posted' => true];
        })(),
        'transfer' => (function () use ($spec) {
            $out = app(AccountTransferService::class)->transfer(
                Account::withoutGlobalScopes()->findOrFail($spec['from']),
                Account::withoutGlobalScopes()->findOrFail($spec['to']),
                $spec['amount'],
                $spec['date'],
            );

            return ['out' => $out['out']->id];
        })(),
        default => throw new InvalidArgumentException('Unknown action ' . $spec['action']),
    };

    echo json_encode($result) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['error' => get_class($e) . ': ' . $e->getMessage()]) . PHP_EOL;
    exit(1);
}

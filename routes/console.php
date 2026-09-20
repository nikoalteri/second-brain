<?php

use App\Enums\CreditCardCycleStatus;
use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Services\CreditCardCycleService;
use App\Services\LoanScheduleService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Proof that the scheduler (the cron entry on the VM) is running: read by /health/scheduler so an
// external monitor can raise an alarm when it stops.
Schedule::call(fn () => Cache::forever('scheduler:heartbeat', now()->timestamp))
    ->name('scheduler-heartbeat')
    ->everyMinute();

Artisan::command('credit-cards:generate-cycles {--month=} {--issue-ready}', function () {
    $service = app(CreditCardCycleService::class);
    $reference = $this->option('month')
        ? Carbon::parse($this->option('month') . '-01')
        : now();

    $cards = CreditCard::query()
        ->withoutUserScope()
        ->where('status', CreditCardStatus::ACTIVE)
        ->get();

    $created = 0;
    $issued = 0;
    $failed = 0;

    // One card's exception must not stop every other card from being processed on the same run
    // — this command is scheduled nightly across every active card, and a single bad row would
    // otherwise silently skip cycle generation/issuance for the rest of the user base.
    foreach ($cards as $card) {
        try {
            $cycle = $service->ensureCurrentMonthCycle($card, $reference->copy());

            if ($cycle->wasRecentlyCreated) {
                $created++;
            }

            if (
                $this->option('issue-ready')
                && $cycle->status === CreditCardCycleStatus::OPEN
                && $reference->toDateString() >= $cycle->statement_date->toDateString()
            ) {
                $service->issueCycle($cycle);
                $issued++;
            }

            $service->refreshCycleStatuses($card->fresh(['cycles.payments', 'payments']));
            $service->syncCardBalance($card->fresh(['cycles.payments', 'payments']));
        } catch (Throwable $e) {
            $failed++;
            Log::error("credit-cards:generate-cycles failed for card {$card->id}", ['exception' => $e]);
            $this->error("Card {$card->id}: {$e->getMessage()}");
        }
    }

    $this->info("Cycles ensured: {$cards->count()} cards, {$created} created, {$issued} issued, {$failed} failed.");

    if ($failed > 0) {
        return Command::FAILURE;
    }
})->purpose('Create monthly credit card cycles and optionally issue ready cycles');

Artisan::command('credit-cards:balance-audit', function () {
    // No date-based threshold: a migration's filename timestamp only says when the file was
    // authored, not when it actually ran in this environment. If deploy happens later than
    // the migration's own timestamp, any card created in between would be silently excluded
    // by a fixed cutoff, while genuinely being exposed to the pre-fix nightly recompute.
    // There is no reliable in-data signal for "was this card created before opening_balance
    // existed in this environment" — so, conservatively, list every active card and let the
    // owner cross-check all of them against real statements, using created_at only as a
    // sorting aid (oldest first) rather than a pass/fail flag.
    $cards = CreditCard::query()
        ->withoutUserScope()
        ->where('status', CreditCardStatus::ACTIVE)
        ->with('user:id,email')
        ->orderBy('created_at')
        ->get();

    $rows = $cards->map(function (CreditCard $card) {
        $expenses = (float) $card->expenses()->sum('amount');
        $paidPrincipal = (float) $card->payments()
            ->where('status', CreditCardPaymentStatus::PAID)
            ->sum('principal_amount');

        return [
            $card->id,
            $card->user?->email ?? '—',
            $card->name,
            $card->type?->value ?? '—',
            $card->created_at->toDateString(),
            number_format((float) $card->current_balance, 2),
            number_format((float) $card->opening_balance, 2),
            number_format($expenses, 2),
            number_format($paidPrincipal, 2),
        ];
    });

    $this->table(
        ['ID', 'User', 'Name', 'Type', 'Created', 'Current balance', 'Opening balance', 'Expenses', 'Paid principal'],
        $rows
    );

    $this->info("{$cards->count()} active card(s) listed, oldest first.");
    $this->line('Cross-check every card above against its real statement and correct \'Opening balance\' in Filament where it differs — there is no reliable way to tell from the data alone which cards were created before this fix was live in this environment, so none are pre-filtered as "safe".');
})->purpose('List active credit cards with a balance breakdown for manual reconciliation after the opening_balance fix (Phase 21) — not scheduled, run manually');

Artisan::command('loans:sync-installments {--date=}', function () {
    $throughDate = $this->option('date')
        ? Carbon::parse($this->option('date'))->endOfDay()
        : now()->endOfDay();

    $scheduleService = app(LoanScheduleService::class);
    $loans = Loan::query()
        ->withoutUserScope()
        ->where('status', 'active')
        ->whereNotNull('start_date')
        ->get();

    $failed = 0;

    foreach ($loans as $loan) {
        try {
            $scheduleService->generate($loan, onlyMissing: true);
        } catch (Throwable $e) {
            $failed++;
            Log::error("loans:sync-installments failed for loan {$loan->id}", ['exception' => $e]);
            $this->error("Loan {$loan->id}: {$e->getMessage()}");
        }
    }

    $this->info("Loans checked and synced through {$throughDate->toDateString()}: {$loans->count()}, {$failed} failed.");

    if ($failed > 0) {
        return Command::FAILURE;
    }
})->purpose('Generate missing loan installments and post due ones to transactions');

Artisan::command('subscriptions:sync-renewals {--date=}', function () {
    $throughDate = $this->option('date')
        ? Carbon::parse($this->option('date'))->endOfDay()
        : now()->endOfDay();

    $service = app(SubscriptionService::class);
    $synced = $service->syncDueRenewals($throughDate);

    $this->info("Subscriptions checked and synced through {$throughDate->toDateString()}: {$synced} renewal(s) processed.");
})->purpose('Post due subscription renewals to transactions or credit card expenses');

Schedule::command('loans:sync-installments')->dailyAt('01:50')->withoutOverlapping();
Schedule::command('subscriptions:sync-renewals')->dailyAt('01:55')->withoutOverlapping();
Schedule::command('credit-cards:generate-cycles --issue-ready')->dailyAt('02:00')->withoutOverlapping();
// Consumed refresh tokens stay for a week past their expiry so their reuse can still be detected.
Schedule::command('sanctum:prune-expired --hours=168')->dailyAt('03:30');

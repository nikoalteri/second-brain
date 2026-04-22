<?php

use App\Enums\CreditCardCycleStatus;
use App\Enums\CreditCardStatus;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Services\CreditCardCycleService;
use App\Services\LoanScheduleService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('credit-cards:generate-cycles {--month=} {--issue-ready}', function () {
    $service = app(CreditCardCycleService::class);
    $reference = $this->option('month')
        ? Carbon::parse($this->option('month') . '-01')
        : now();

    $cards = CreditCard::query()
        ->where('status', CreditCardStatus::ACTIVE)
        ->get();

    $created = 0;
    $issued = 0;

    foreach ($cards as $card) {
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
    }

    $this->info("Cycles ensured: {$cards->count()} cards, {$created} created, {$issued} issued.");
})->purpose('Create monthly credit card cycles and optionally issue ready cycles');

Artisan::command('loans:sync-installments {--date=}', function () {
    $throughDate = $this->option('date')
        ? Carbon::parse($this->option('date'))->endOfDay()
        : now()->endOfDay();

    $scheduleService = app(LoanScheduleService::class);
    $loans = Loan::query()
        ->where('status', 'active')
        ->whereNotNull('start_date')
        ->get();

    foreach ($loans as $loan) {
        $scheduleService->generate($loan, onlyMissing: true);
    }

    $this->info("Loans checked and synced through {$throughDate->toDateString()}: {$loans->count()}.");
})->purpose('Generate missing loan installments and post due ones to transactions');

Artisan::command('subscriptions:sync-renewals {--date=}', function () {
    $throughDate = $this->option('date')
        ? Carbon::parse($this->option('date'))->endOfDay()
        : now()->endOfDay();

    $service = app(SubscriptionService::class);
    $synced = $service->syncDueRenewals($throughDate);

    $this->info("Subscriptions checked and synced through {$throughDate->toDateString()}: {$synced} renewal(s) processed.");
})->purpose('Post due subscription renewals to transactions or credit card expenses');

Schedule::command('loans:sync-installments')->dailyAt('01:50');
Schedule::command('subscriptions:sync-renewals')->dailyAt('01:55');
Schedule::command('credit-cards:generate-cycles --issue-ready')->dailyAt('02:00');

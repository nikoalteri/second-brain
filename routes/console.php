<?php

use App\Enums\CreditCardCycleStatus;
use App\Enums\CreditCardStatus;
use App\Models\CreditCard;
use App\Services\CreditCardCycleService;
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

Schedule::command('credit-cards:generate-cycles --issue-ready')->dailyAt('02:00');

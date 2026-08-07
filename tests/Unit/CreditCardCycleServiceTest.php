<?php

namespace Tests\Unit;

use App\Enums\CreditCardCycleStatus;
use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Services\CreditCardCycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreditCardCycleServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function revolving_breakdown_with_12_percent_rate(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'stamp_duty_amount' => 2,
        ]);

        $result = $service->calculateRevolvingPaymentBreakdown($card, 1000);

        // 12% annual = 1% monthly of 1000 = 10, principal = 250 - 10 = 240
        $this->assertSame(10.0, $result['interest_amount']);
        $this->assertSame(240.0, $result['principal_amount']);
        $this->assertSame(250.0, $result['installment_amount']);
        $this->assertSame(252.0, $result['total_due']);
        $this->assertSame(760.0, $result['next_balance']);
        $this->assertFalse($result['invalid_installment']);
    }

    #[Test]
    public function revolving_breakdown_splits_interest_and_principal_at_14_percent(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 14,
            'stamp_duty_amount' => 2,
        ]);

        $result = $service->calculateRevolvingPaymentBreakdown($card, 600);

        // 14% annual = 7/6 % monthly of 600 = 7.00, principal = 250 - 7 = 243
        $this->assertSame(7.0, $result['interest_amount']);
        $this->assertSame(243.0, $result['principal_amount']);
        $this->assertSame(250.0, $result['installment_amount']);
        $this->assertSame(252.0, $result['total_due']);
        $this->assertSame(357.0, $result['next_balance']);
        $this->assertFalse($result['invalid_installment']);
    }

    #[Test]
    public function revolving_breakdown_marks_invalid_when_installment_does_not_cover_interest(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 5,
            'interest_rate' => 24,
            'stamp_duty_amount' => 2,
        ]);

        // 24% annual = 2% monthly of 5000 = 100, which still exceeds the 5 installment
        $result = $service->calculateRevolvingPaymentBreakdown($card, 5000);

        $this->assertTrue($result['invalid_installment']);
        $this->assertSame(100.0, $result['interest_amount']);
        $this->assertSame(2.0, $result['stamp_duty_amount']);
        $this->assertSame(7.0, $result['total_due']);
    }

    #[Test]
    public function revolving_breakdown_is_invalid_when_fixed_installment_is_missing(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 0,
            'interest_rate' => 12,
            'stamp_duty_amount' => 2,
        ]);

        $result = $service->calculateRevolvingPaymentBreakdown($card, 1000);

        $this->assertTrue($result['invalid_installment']);
        $this->assertSame(2.0, $result['total_due']);
    }

    #[Test]
    public function revolving_breakdown_caps_installment_when_balance_is_lower_than_max_installment(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'stamp_duty_amount' => 2,
        ]);

        // 12% annual = 1% monthly of 100 = 1, principal = min(100, 250 - 1) = 100
        $result = $service->calculateRevolvingPaymentBreakdown($card, 100);

        $this->assertSame(1.0, $result['interest_amount']);
        $this->assertSame(100.0, $result['principal_amount']);
        $this->assertSame(101.0, $result['installment_amount']);
        $this->assertSame(103.0, $result['total_due']);
        $this->assertSame(0.0, $result['next_balance']);
        $this->assertFalse($result['invalid_installment']);
    }

    #[Test]
    public function charge_card_breakdown_has_no_interest(): void
    {
        $service = new CreditCardCycleService();

        $card = new CreditCard([
            'type' => CreditCardType::CHARGE,
            'fixed_payment' => 250,
            'interest_rate' => 14,
            'stamp_duty_amount' => 0,
        ]);

        $result = $service->calculateRevolvingPaymentBreakdown($card, 500);

        $this->assertSame(0.0, $result['interest_amount']);
        $this->assertSame(0.0, $result['principal_amount']);
        $this->assertSame(250.0, $result['installment_amount']);
        $this->assertSame(250.0, $result['total_due']);
        $this->assertSame(500.0, $result['next_balance']);
    }

    #[Test]
    public function ensure_current_month_cycle_anchors_period_start_to_previous_cycle_statement_date(): void
    {
        $card = CreditCard::factory()->create(['statement_day' => 6]);

        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => '2027-05-07',
            'statement_date' => '2027-06-06',
            'status' => CreditCardCycleStatus::PAID,
        ]);

        $service = app(CreditCardCycleService::class);
        $cycle = $service->ensureCurrentMonthCycle($card, Carbon::parse('2027-07-03'));

        $this->assertSame('2027-06-07', $cycle->period_start_date->toDateString());
        $this->assertSame('2027-07-06', $cycle->statement_date->toDateString());
    }

    #[Test]
    public function ensure_current_month_cycle_falls_back_to_month_start_with_no_previous_cycle(): void
    {
        $card = CreditCard::factory()->create(['statement_day' => 6]);

        $service = app(CreditCardCycleService::class);
        $cycle = $service->ensureCurrentMonthCycle($card, Carbon::parse('2027-07-03'));

        $this->assertSame('2027-07-01', $cycle->period_start_date->toDateString());
        $this->assertSame('2027-07-06', $cycle->statement_date->toDateString());
    }

    #[Test]
    public function ensure_current_month_cycle_clamps_statement_day_31_into_short_month(): void
    {
        $card = CreditCard::factory()->create(['statement_day' => 31]);

        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => '2027-01-01',
            'statement_date' => '2027-01-31',
            'status' => CreditCardCycleStatus::PAID,
        ]);

        $service = app(CreditCardCycleService::class);
        $cycle = $service->ensureCurrentMonthCycle($card, Carbon::parse('2027-02-15'));

        $this->assertSame('2027-02-28', $cycle->statement_date->toDateString());
        $this->assertSame('2027-02-01', $cycle->period_start_date->toDateString());
    }

    #[Test]
    public function ensure_current_month_cycle_clamps_statement_day_30_into_short_month(): void
    {
        $card = CreditCard::factory()->create(['statement_day' => 30]);

        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => '2027-02-01',
            'statement_date' => '2027-02-28',
            'status' => CreditCardCycleStatus::PAID,
        ]);

        $service = app(CreditCardCycleService::class);
        $cycle = $service->ensureCurrentMonthCycle($card, Carbon::parse('2027-03-10'));

        $this->assertSame('2027-03-30', $cycle->statement_date->toDateString());
        $this->assertSame('2027-03-01', $cycle->period_start_date->toDateString());
    }

    #[Test]
    public function ensure_current_month_cycle_is_idempotent_for_same_statement_date(): void
    {
        $card = CreditCard::factory()->create(['statement_day' => 6]);

        $service = app(CreditCardCycleService::class);
        $first = $service->ensureCurrentMonthCycle($card, Carbon::parse('2027-07-03'));
        $second = $service->ensureCurrentMonthCycle($card, Carbon::parse('2027-07-20'));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CreditCardCycle::count());
    }

    /**
     * Builds a CHARGE card with a 300.00 expense, issues its cycle (total_due = 300.00),
     * and returns [card, cycle, payment].
     */
    private function makeIssuedChargeCycleFixture(): array
    {
        $account = Account::factory()->create(['balance' => 1000]);

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Race Condition Test Card',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 0,
        ]);

        CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => now()->toDateString(),
            'amount' => 300,
            'description' => 'Expense before statement issue',
        ]);

        $cycle = CreditCardCycle::query()
            ->where('credit_card_id', $card->id)
            ->firstOrFail();

        $issued = app(CreditCardCycleService::class)->issueCycle($cycle);
        $this->assertTrue($issued);

        $cycle->refresh();
        $card->refresh();

        $payment = $cycle->payments()->firstOrFail();

        return [$card, $cycle, $payment];
    }

    #[Test]
    public function duplicate_payment_sync_with_stale_previous_status_does_not_double_reduce_balance(): void
    {
        [$card, , $payment] = $this->makeIssuedChargeCycleFixture();

        $this->assertSame(300.0, (float) $payment->principal_amount);

        // Simulate the first racing request: the observer fires from a real status update.
        $payment->update(['status' => CreditCardPaymentStatus::PAID, 'actual_date' => now()->toDateString()]);

        // The update above already fires the observer once. Capture the post-observer state:
        $card->refresh();
        $balanceAfterFirstSync = (float) $card->current_balance;

        // Simulate the racing second request: same stale previousStatus the first request read.
        app(CreditCardCycleService::class)->syncCycleAndCardFromPayment($payment->id, 'pending', 'paid');

        $card->refresh();
        $this->assertSame(
            $balanceAfterFirstSync,
            (float) $card->current_balance,
            'A duplicate sync carrying the same stale previousStatus must not reduce the balance a second time'
        );
        $this->assertSame(0.0, (float) $card->current_balance);
    }

    #[Test]
    public function duplicate_payment_unmark_sync_does_not_double_restore_balance(): void
    {
        [$card, , $payment] = $this->makeIssuedChargeCycleFixture();

        $payment->update(['status' => CreditCardPaymentStatus::PAID, 'actual_date' => now()->toDateString()]);
        $card->refresh();
        $this->assertSame(0.0, (float) $card->current_balance);

        // Simulate the first racing "unmark" request.
        $payment->update(['status' => CreditCardPaymentStatus::PENDING, 'actual_date' => null]);
        $card->refresh();
        $balanceAfterFirstUnmarkSync = (float) $card->current_balance;

        // Simulate the racing second request: same stale previousStatus the first request read.
        app(CreditCardCycleService::class)->syncCycleAndCardFromPayment($payment->id, 'paid', 'pending');

        $card->refresh();
        $this->assertSame(
            $balanceAfterFirstUnmarkSync,
            (float) $card->current_balance,
            'A duplicate unmark sync carrying the same stale previousStatus must not restore the balance a second time'
        );
        $this->assertSame(300.0, (float) $card->current_balance);
    }

    #[Test]
    public function interleaved_payment_syncs_produce_deterministic_cycle_status(): void
    {
        [, $cycle, $payment] = $this->makeIssuedChargeCycleFixture();

        $payment->update(['status' => CreditCardPaymentStatus::PAID, 'actual_date' => now()->toDateString()]);

        $service = app(CreditCardCycleService::class);
        $service->syncCycleAndCardFromPayment($payment->id, 'pending', 'paid');
        $this->assertSame(CreditCardCycleStatus::PAID, $cycle->fresh()->status);
        $this->assertSame(300.0, (float) $cycle->fresh()->paid_amount);

        $service->syncCycleAndCardFromPayment($payment->id, 'pending', 'paid');
        $this->assertSame(CreditCardCycleStatus::PAID, $cycle->fresh()->status);
        $this->assertSame(300.0, (float) $cycle->fresh()->paid_amount);
    }
}

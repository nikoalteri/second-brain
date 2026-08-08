<?php

namespace Tests\Feature;

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

/**
 * End-to-end synthetic regression proving D-02 (cycle period derivation) and D-03 (both
 * stamp-duty inclusion modes) compose correctly through the real CreditCardCycleService flow.
 *
 * Every figure below is fictional and dated in 2027 — none of it comes from the gitignored
 * real-statement reference documents (D-04).
 */
class RevolvingInterestEngineRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds the shared fixture: a fictional revolving card with a paid first cycle
     * (statement day 6, no prior debt), ready to derive its second cycle from.
     *
     * @return array{0: Account, 1: CreditCard, 2: CreditCardCycle}
     */
    private function makeCardWithPaidFirstCycle(bool $includesStampDuty): array
    {
        $account = Account::factory()->create(['balance' => 5000]);

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Fixture Revolving Card',
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 4000,
            'fixed_payment' => 250,
            'interest_rate' => 14,
            'stamp_duty_amount' => 2,
            'fixed_payment_includes_stamp_duty' => $includesStampDuty,
            'statement_day' => 6,
            'due_day' => 20,
            'skip_weekends' => false,
            'interest_calculation_method' => 'daily_balance',
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'start_date' => Carbon::parse('2027-03-01'),
        ]);

        $cycleA = app(CreditCardCycleService::class)
            ->ensureCurrentMonthCycle($card, Carbon::parse('2027-04-03'));

        // Nothing was spent or issued on cycle A, so total_due/paid_amount both stay 0 and
        // CreditCardCycleObserver::updated() short-circuits without creating a spurious payment.
        $cycleA->update(['status' => CreditCardCycleStatus::PAID]);

        return [$account, $card, $cycleA];
    }

    #[Test]
    public function second_cycle_period_starts_the_day_after_the_previous_statement_date(): void
    {
        [, $card, $cycleA] = $this->makeCardWithPaidFirstCycle(false);

        // First-cycle anchor: calendar-month start of the statement's month.
        $this->assertSame('2027-04-01', $cycleA->period_start_date->toDateString());
        $this->assertSame('2027-04-06', $cycleA->statement_date->toDateString());

        $cycleB = app(CreditCardCycleService::class)
            ->ensureCurrentMonthCycle($card, Carbon::parse('2027-05-02'));

        // D-02: the second cycle starts the day AFTER the previous cycle's statement date.
        $this->assertSame('2027-04-07', $cycleB->period_start_date->toDateString());
        $this->assertSame('2027-05-06', $cycleB->statement_date->toDateString());
        $this->assertSame('2027-05', $cycleB->period_month);

        $cycleBAgain = app(CreditCardCycleService::class)
            ->ensureCurrentMonthCycle($card, Carbon::parse('2027-05-04'));

        $this->assertSame($cycleB->id, $cycleBAgain->id);
        $this->assertSame(2, CreditCardCycle::query()->count());
    }

    #[Test]
    public function inclusive_stamp_duty_card_bills_total_due_equal_to_its_fixed_payment(): void
    {
        [, $card] = $this->makeCardWithPaidFirstCycle(true);

        $cycleB = app(CreditCardCycleService::class)
            ->ensureCurrentMonthCycle($card, Carbon::parse('2027-05-02'));

        CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2027-04-20'),
            'amount' => 1000.00,
            'description' => 'Synthetic fixture expense for the D-03 inclusive-mode regression',
        ]);

        // Normalize the fixture explicitly after the expense's side effects.
        $cycleB->update(['total_spent' => 1000.00]);
        $card->update(['current_balance' => 1000.00]);
        $card->refresh();
        $cycleB->refresh();

        $issued = app(CreditCardCycleService::class)->issueCycle($cycleB);
        $this->assertTrue($issued);

        $cycleB->refresh();

        // Daily walk: opening 1000 - 1000 + 0 = 0 for 2027-04-07..2027-04-19 (13 days), then
        // 1000 for 2027-04-20..2027-05-06 (17 days) = 17000 balance-days;
        // 17000 x 0.14 / 365 = 6.5205 -> 6.52. Inclusive split: 250 - 6.52 - 2 = 241.48,
        // total due = the fixed payment itself (250.00), since the duty is absorbed inside it.
        $this->assertSame(6.52, (float) $cycleB->interest_amount);
        $this->assertSame(241.48, (float) $cycleB->principal_amount);
        $this->assertSame(2.0, (float) $cycleB->stamp_duty_amount);
        $this->assertSame(250.0, (float) $cycleB->total_due);

        $payment = $cycleB->payments()->firstOrFail();
        $payment->update([
            'status' => CreditCardPaymentStatus::PAID,
            'actual_date' => Carbon::parse('2027-05-25'),
        ]);

        // Marking the payment PAID reduces the card balance by exactly the principal portion:
        // 1000 (expenses) - 241.48 (paid principal) = 758.52.
        $this->assertSame(758.52, (float) $card->fresh()->current_balance);
    }

    #[Test]
    public function exclusive_stamp_duty_card_bills_fixed_payment_plus_duty(): void
    {
        [, $card] = $this->makeCardWithPaidFirstCycle(false);

        $cycleB = app(CreditCardCycleService::class)
            ->ensureCurrentMonthCycle($card, Carbon::parse('2027-05-02'));

        CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2027-04-20'),
            'amount' => 1000.00,
            'description' => 'Synthetic fixture expense for the D-03 exclusive-mode regression',
        ]);

        $cycleB->update(['total_spent' => 1000.00]);
        $card->update(['current_balance' => 1000.00]);
        $card->refresh();
        $cycleB->refresh();

        $issued = app(CreditCardCycleService::class)->issueCycle($cycleB);
        $this->assertTrue($issued);

        $cycleB->refresh();

        // Same daily walk as the inclusive-mode test (6.52 interest); exclusive mode keeps the
        // stamp duty on top: principal = 250 - 6.52 = 243.48, total due = 250 + 2 = 252.00.
        $this->assertSame(6.52, (float) $cycleB->interest_amount);
        $this->assertSame(243.48, (float) $cycleB->principal_amount);
        $this->assertSame(2.0, (float) $cycleB->stamp_duty_amount);
        $this->assertSame(252.0, (float) $cycleB->total_due);
    }
}

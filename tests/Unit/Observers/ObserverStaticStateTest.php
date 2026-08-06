<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Observers\CreditCardExpenseObserver;
use App\Observers\CreditCardPaymentObserver;
use App\Services\CreditCardCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ObserverStaticStateTest extends TestCase
{
    use RefreshDatabase;

    private function staticState(string $observerClass, string $property): array
    {
        $reflection = new \ReflectionProperty($observerClass, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue();
    }

    /**
     * Builds a CHARGE card with a 300.00 expense, issues its cycle (total_due = 300.00),
     * and returns [card, cycle, payment]. Mirrors the fixture used by
     * tests/Unit/CreditCardCycleServiceTest.php for consistency.
     */
    private function makeIssuedChargeCycleFixture(): array
    {
        $account = Account::factory()->create(['balance' => 1000]);

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Observer State Test Card',
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
    public function test_expense_observer_clears_static_pointers_after_successful_update(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Expense Observer Card',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 0,
        ]);

        CreditCardCycle::create([
            'credit_card_id' => $card->id,
            'period_month' => now()->format('Y-m'),
            'period_start_date' => now()->startOfMonth(),
            'statement_date' => now()->endOfMonth(),
            'due_date' => now()->endOfMonth()->addDays(15),
            'total_spent' => 0,
            'status' => \App\Enums\CreditCardCycleStatus::OPEN,
        ]);

        $expense = CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => now()->toDateString(),
            'amount' => 100,
            'description' => 'Original expense',
        ]);

        $expense->update(['amount' => 150]);

        $this->assertSame([], $this->staticState(CreditCardExpenseObserver::class, 'originalPointers'));
    }

    #[Test]
    public function test_payment_observer_clears_static_statuses_after_successful_update(): void
    {
        [, , $payment] = $this->makeIssuedChargeCycleFixture();

        $payment->update(['status' => CreditCardPaymentStatus::PAID, 'actual_date' => now()->toDateString()]);

        $this->assertSame([], $this->staticState(CreditCardPaymentObserver::class, 'previousStatuses'));
    }

    #[Test]
    public function test_failed_write_does_not_leave_contaminating_static_state(): void
    {
        [$card, $cycle, $payment] = $this->makeIssuedChargeCycleFixture();

        $this->assertSame(CreditCardPaymentStatus::PENDING, $payment->status);

        try {
            $payment->update(['credit_card_id' => 999999]);
        } catch (\Throwable $e) {
            // Expected: the DB layer rejects the FK, after CreditCardPaymentObserver::updating already ran.
        }

        $residue = $this->staticState(CreditCardPaymentObserver::class, 'previousStatuses');

        if ($residue === []) {
            // No contamination window was reproducible: the FK violation aborted the transaction
            // before `updated()` (and therefore before observer bookkeeping) could leave residue.
            $this->assertSame([], $residue);

            return;
        }

        // A residual entry survived the failed write. Prove whether it corrupts a subsequent
        // legitimate update of the same record.
        $this->assertArrayHasKey($payment->id, $residue);

        $payment->refresh();
        $payment->update(['status' => CreditCardPaymentStatus::PAID, 'actual_date' => now()->toDateString()]);

        $cycle->refresh();
        $card->refresh();

        $this->assertSame(\App\Enums\CreditCardCycleStatus::PAID, $cycle->status);
        $this->assertSame(0.0, (float) $card->current_balance);
    }

    #[Test]
    public function test_sequential_expense_updates_do_not_cross_contaminate(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Sequential Updates Card',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 0,
        ]);

        CreditCardCycle::create([
            'credit_card_id' => $card->id,
            'period_month' => now()->format('Y-m'),
            'period_start_date' => now()->startOfMonth(),
            'statement_date' => now()->endOfMonth(),
            'due_date' => now()->endOfMonth()->addDays(15),
            'total_spent' => 0,
            'status' => \App\Enums\CreditCardCycleStatus::OPEN,
        ]);

        $expenseA = CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => now()->toDateString(),
            'amount' => 100,
            'description' => 'Expense A',
        ]);

        $expenseB = CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => now()->toDateString(),
            'amount' => 200,
            'description' => 'Expense B',
        ]);

        $expenseC = CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => now()->toDateString(),
            'amount' => 300,
            'description' => 'Expense C',
        ]);

        $expenseA->update(['amount' => 110]);
        $expenseB->update(['amount' => 220]);
        $expenseC->update(['amount' => 330]);

        $this->assertSame(660.0, (float) $card->fresh()->current_balance);
        $this->assertSame([], $this->staticState(CreditCardExpenseObserver::class, 'originalPointers'));
    }
}

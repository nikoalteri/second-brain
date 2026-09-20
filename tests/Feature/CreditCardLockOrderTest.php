<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CreditCardPaymentStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use App\Models\User;
use App\Services\CreditCardCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SQLite ignores FOR UPDATE, so these tests cannot show two connections blocking each other.
 * They pin the ORDER in which the services read and write, which is what keeps concurrent
 * expense, payment and cycle writes on one card from deadlocking or overwriting each other.
 */
class CreditCardLockOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id]);
    }

    private function card(): CreditCard
    {
        return CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
    }

    /** @return list<string> the SQL of every query run by the callback, in order */
    private function queriesOf(callable $callback): array
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql.' '.json_encode($query->bindings);
        });

        $callback();

        return $queries;
    }

    private function firstIndex(array $queries, string $needle): int|false
    {
        foreach ($queries as $index => $sql) {
            if (str_contains($sql, $needle)) {
                return $index;
            }
        }

        return false;
    }

    private function lockedCardIds(array $queries): array
    {
        $ids = [];
        foreach ($queries as $sql) {
            if (preg_match('/from "credit_cards" where "credit_cards"\."id" = \? .*\[(\d+)\]$/', $sql, $match)) {
                $ids[] = (int) $match[1];
            }
        }

        return $ids;
    }

    public function test_a_payment_update_locks_the_card_before_it_writes_the_cycle(): void
    {
        $card = $this->card();
        $cycle = CreditCardCycle::factory()->issued()->create(['credit_card_id' => $card->id, 'total_due' => 100]);
        $payment = CreditCardPayment::create([
            'credit_card_id' => $card->id, 'credit_card_cycle_id' => $cycle->id, 'due_date' => now(), 'installment_amount' => 100,
            'interest_amount' => 0, 'principal_amount' => 100, 'stamp_duty_amount' => 0, 'total_amount' => 100,
            'status' => CreditCardPaymentStatus::PENDING,
        ]);

        // syncCycleAndCardFromPayment() sums PAID payments straight from the database to compute
        // the cycle's new paid_amount/status — the $previousStatus/$currentStatus strings below are
        // only used for the overdue-transition heuristic, not for this sum. Without persisting the
        // status first, the sum stays 0, the computed paid_amount/status end up identical to the
        // cycle's existing values, Eloquent sees nothing dirty and silently skips the UPDATE
        // entirely — which intermittently failed this test depending on incidental global date
        // state from other tests (whether "now" happened to be past the cycle's due date).
        $payment->forceFill(['status' => CreditCardPaymentStatus::PAID])->saveQuietly();

        $queries = $this->queriesOf(fn () => app(CreditCardCycleService::class)->syncCycleAndCardFromPayment($payment->id, 'pending', 'paid'));

        $cardLock = $this->firstIndex($queries, 'from "credit_cards" where "credit_cards"."id" = ?');
        $cycleWrite = $this->firstIndex($queries, 'update "credit_card_cycles"');

        $this->assertNotFalse($cardLock, 'the card row is never locked');
        $this->assertNotFalse($cycleWrite, 'the cycle write never happened');
        $this->assertLessThan($cycleWrite, $cardLock);
    }

    public function test_the_balance_recompute_locks_the_card_before_it_reads_the_totals(): void
    {
        $card = $this->card();

        $queries = $this->queriesOf(fn () => app(CreditCardCycleService::class)->syncCardBalance($card));

        $cardLock = $this->firstIndex($queries, 'from "credit_cards" where "credit_cards"."id" = ?');
        $expenseSum = $this->firstIndex($queries, 'sum("amount")');

        $this->assertNotFalse($cardLock, 'the card row is never locked');
        $this->assertNotFalse($expenseSum);
        $this->assertLessThan($expenseSum, $cardLock);
    }

    public function test_moving_an_expense_locks_both_cards_in_ascending_id_order(): void
    {
        $lower = $this->card();
        $higher = $this->card();
        $expense = CreditCardExpense::create([
            'credit_card_id' => $lower->id, 'spent_at' => now(), 'amount' => 25, 'description' => 'Move me',
        ]);

        // Moving from the lower to the higher id: locking the target first would go 2 then 1.
        $queries = $this->queriesOf(fn () => $expense->update(['credit_card_id' => $higher->id]));

        $ids = array_values(array_unique($this->lockedCardIds($queries)));

        $this->assertSame([$lower->id, $higher->id], array_slice($ids, 0, 2));
    }
}

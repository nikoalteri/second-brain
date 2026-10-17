<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Loan;
use App\Models\SavingGoal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashFlowForecastApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/dashboard/cash-flow-forecast';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(User $user, float $balance, string $currency = 'EUR', array $extra = []): Account
    {
        return Account::factory()->create($extra + [
            'user_id' => $user->id, 'balance' => $balance, 'currency' => $currency, 'is_active' => true,
        ]);
    }

    private function installment(User $user, Account $account, string $due, float $amount = 250): int
    {
        $loan = Loan::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id, 'start_date' => '2026-10-01',
            'withdrawal_day' => 1, 'total_installments' => 2, 'monthly_payment' => $amount,
        ]);
        $payment = $loan->payments()->create(['due_date' => $due, 'amount' => $amount, 'status' => 'pending']);
        DB::table('loan_payments')->where('id', $payment->id)->update(['status' => 'pending']);

        return $payment->id;
    }

    private function currency(array $payload, string $code): array
    {
        return collect($payload['data']['currencies'])->firstWhere('currency', $code);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_single_currency_estimate_subtracts_counted_commitments(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, 1000);
        $this->installment($user, $account, '2026-10-25');

        Sanctum::actingAs($user);
        $response = $this->getJson(self::URL)->assertOk();

        $response->assertJsonPath('data.as_of', '2026-10-17')
            ->assertJsonPath('data.through_date', '2026-11-16')
            ->assertJsonPath('data.horizon_days', 30);

        $eur = $this->currency($response->json(), 'EUR');
        $this->assertSame('1000.00', $eur['available_balance']);
        $this->assertSame('250.00', $eur['counted_commitments']);
        $this->assertSame('750.00', $eur['estimated_remaining']);
        $this->assertCount(1, $eur['obligations']);
        $this->assertSame('250.00', $eur['obligations'][0]['cash_outflow_amount']);
    }

    public function test_mixed_currencies_yield_separate_estimates_and_no_grand_total(): void
    {
        $user = User::factory()->create();
        $eur = $this->account($user, 1000, 'EUR');
        $usd = $this->account($user, 500, 'USD');
        $this->installment($user, $eur, '2026-10-25', 100);
        $this->installment($user, $usd, '2026-10-26', 40);

        Sanctum::actingAs($user);
        $json = $this->getJson(self::URL)->assertOk()->json();

        $this->assertCount(2, $json['data']['currencies']);
        $this->assertSame('900.00', $this->currency($json, 'EUR')['estimated_remaining']);
        $this->assertSame('460.00', $this->currency($json, 'USD')['estimated_remaining']);
        $this->assertArrayNotHasKey('estimated_remaining', $json['data']);
        $this->assertArrayNotHasKey('total', $json['data']);
    }

    public function test_goal_linked_account_is_excluded_in_full_with_its_obligations(): void
    {
        $user = User::factory()->create();
        $free = $this->account($user, 300);
        $goal = $this->account($user, 5000);
        SavingGoal::factory()->create(['user_id' => $user->id, 'account_id' => $goal->id]);
        $this->installment($user, $free, '2026-10-20', 50);
        $this->installment($user, $goal, '2026-10-21', 70);

        Sanctum::actingAs($user);
        $eur = $this->currency($this->getJson(self::URL)->assertOk()->json(), 'EUR');

        $this->assertSame('300.00', $eur['available_balance']);
        $this->assertSame('50.00', $eur['counted_commitments']);
        $this->assertSame('250.00', $eur['estimated_remaining']);
        $this->assertSame(1, $eur['included_account_count']);
        $this->assertSame(1, $eur['excluded_account_count']);

        $excluded = collect($eur['obligations'])->firstWhere('cash_outflow_amount', '0.00');
        $this->assertSame('source_account_excluded', $excluded['reason']);
        $this->assertSame('70.00', $excluded['face_amount']);
    }

    public function test_archived_goal_does_not_exclude_its_account(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, 800);
        SavingGoal::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'archived']);

        Sanctum::actingAs($user);
        $eur = $this->currency($this->getJson(self::URL)->assertOk()->json(), 'EUR');

        $this->assertSame('800.00', $eur['available_balance']);
    }

    public function test_inactive_accounts_are_neither_balance_nor_deduction(): void
    {
        $user = User::factory()->create();
        $active = $this->account($user, 200);
        $inactive = $this->account($user, 900, 'EUR', ['is_active' => false]);
        $this->installment($user, $inactive, '2026-10-22', 60);

        Sanctum::actingAs($user);
        $json = $this->getJson(self::URL)->assertOk()->json();
        $eur = $this->currency($json, 'EUR');

        $this->assertSame('200.00', $eur['available_balance']);
        $this->assertSame('0.00', $eur['counted_commitments']);
        $this->assertSame(1, $json['data']['coverage']['inactive_account_count']);
        $this->assertSame($active->id, Account::where('balance', 200)->value('id'));
    }

    public function test_installment_already_posted_to_the_balance_is_not_deducted_twice(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, 1000);
        $paymentId = $this->installment($user, $account, '2026-10-25');
        // Pending but already posted: the balance has moved, so the estimate must not subtract it again.
        DB::table('transactions')->insert([
            'user_id' => $user->id, 'account_id' => $account->id, 'loan_payment_id' => $paymentId,
            'transaction_type_id' => DB::table('transaction_types')->insertGetId(['name' => 'Loan installment payment', 'is_income' => false, 'created_at' => now(), 'updated_at' => now()]),
            'amount' => -250, 'date' => '2026-10-25', 'description' => 'posting',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $eur = $this->currency($this->getJson(self::URL)->assertOk()->json(), 'EUR');

        $this->assertSame('0.00', $eur['counted_commitments']);
        $this->assertSame('1000.00', $eur['estimated_remaining']);
        $this->assertSame('already_in_balance', $eur['obligations'][0]['reason']);
    }

    public function test_overdue_item_is_listed_separately_and_flags_the_estimate_incomplete(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, 1000);
        $paymentId = $this->installment($user, $account, '2026-10-10');
        DB::table('transactions')->where('loan_payment_id', $paymentId)->delete();

        Sanctum::actingAs($user);
        $json = $this->getJson(self::URL)->assertOk()->json();
        $eur = $this->currency($json, 'EUR');

        $this->assertSame([], $eur['obligations']);
        $this->assertCount(1, $eur['overdue_obligations']);
        $this->assertSame('0.00', $eur['counted_commitments']);
        $this->assertFalse($eur['complete']);
        $this->assertContains('overdue_obligations', $eur['coverage_flags']);
        $this->assertFalse($json['data']['coverage']['complete']);
        $this->assertContains('overdue_obligations', array_column($json['data']['coverage']['flags'], 'code'));
    }

    public function test_estimate_is_never_labelled_complete_while_bank_data_is_unreconciled(): void
    {
        $user = User::factory()->create();
        $this->account($user, 100);

        Sanctum::actingAs($user);
        $json = $this->getJson(self::URL)->assertOk()->json();

        $this->assertFalse($json['data']['coverage']['complete']);
        $this->assertContains('bank_data_not_reconciled', array_column($json['data']['coverage']['flags'], 'code'));
    }

    public function test_zero_balance_and_negative_result_are_reported_unclamped(): void
    {
        $user = User::factory()->create();
        $zero = $this->account($user, 0);
        $this->installment($user, $zero, '2026-10-20', 120.5);

        Sanctum::actingAs($user);
        $eur = $this->currency($this->getJson(self::URL)->assertOk()->json(), 'EUR');

        $this->assertSame('0.00', $eur['available_balance']);
        $this->assertSame('-120.50', $eur['estimated_remaining']);
    }

    public function test_user_without_accounts_gets_an_empty_currency_list(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(self::URL)->assertOk()->assertJsonPath('data.currencies', []);
    }

    public function test_unknown_currency_account_is_disclosed_and_not_summed(): void
    {
        $user = User::factory()->create();
        $this->account($user, 400, 'EUR');
        $this->account($user, 999, '');

        Sanctum::actingAs($user);
        $json = $this->getJson(self::URL)->assertOk()->json();

        $this->assertCount(1, $json['data']['currencies']);
        $this->assertSame('400.00', $json['data']['currencies'][0]['available_balance']);
        $flags = collect($json['data']['coverage']['flags'])->keyBy('code');
        $this->assertSame(1, $flags['unknown_currency']['count']);
    }

    public function test_other_users_data_and_names_never_appear_even_for_a_superadmin(): void
    {
        $stranger = User::factory()->create();
        $strangerAccount = $this->account($stranger, 7777);
        $this->installment($stranger, $strangerAccount, '2026-10-20');

        Role::findOrCreate('superadmin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $own = $this->account($admin, 50);

        Sanctum::actingAs($admin);
        $json = $this->getJson(self::URL)->assertOk()->json();

        $this->assertCount(1, $json['data']['currencies']);
        $this->assertSame('50.00', $json['data']['currencies'][0]['available_balance']);
        $this->assertSame([], $json['data']['currencies'][0]['obligations']);
        $this->assertStringNotContainsString('7777', json_encode($json));
        $this->assertSame($own->id, Account::where('user_id', $admin->id)->value('id'));
    }

    public function test_debt_and_legacy_credit_card_accounts_are_not_spendable_balance(): void
    {
        $user = User::factory()->create();
        $this->account($user, 500);
        $debt = $this->account($user, -2000, 'EUR', ['type' => 'debt']);
        $this->account($user, -300, 'EUR', ['type' => 'credit_card']);
        $this->installment($user, $debt, '2026-10-22', 80);

        Sanctum::actingAs($user);
        $json = $this->getJson(self::URL)->assertOk()->json();
        $eur = $this->currency($json, 'EUR');

        $this->assertSame('500.00', $eur['available_balance']);
        $this->assertSame('0.00', $eur['counted_commitments']);
        $this->assertSame(1, $eur['included_account_count']);
        $this->assertSame(2, $json['data']['coverage']['non_spendable_account_count']);
    }

    public function test_paid_installment_without_account_movement_is_flagged(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user, 1000);
        $loan = Loan::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id, 'start_date' => '2026-10-01',
            'withdrawal_day' => 1, 'total_installments' => 2, 'monthly_payment' => 250,
        ]);
        $payment = $loan->payments()->create(['due_date' => '2026-10-12', 'amount' => 250, 'status' => 'paid']);
        DB::table('transactions')->where('loan_payment_id', $payment->id)->delete();

        Sanctum::actingAs($user);
        $json = $this->getJson(self::URL)->assertOk()->json();

        $this->assertContains('paid_without_posting', array_column($json['data']['coverage']['flags'], 'code'));
        $this->assertContains('paid_without_posting', $this->currency($json, 'EUR')['coverage_flags']);
        $this->assertSame('0.00', $this->currency($json, 'EUR')['counted_commitments']);
    }
}

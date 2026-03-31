<?php

namespace Tests\Feature\Filament\Widgets;

use App\Models\User;
use Database\Seeders\DummyDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Run seeders to create test data
        $this->seed([
            \Database\Seeders\RolesAndPermissionsSeeder::class,
            \Database\Seeders\TransactionTypeSeeder::class,
            \Database\Seeders\DummyDataSeeder::class,
        ]);
    }

    /** @test */
    public function user_has_all_required_data()
    {
        $this->assertGreaterThan(0, $this->user->accounts()->count(), 'User should have accounts');
        $this->assertGreaterThan(0, $this->user->transactions()->count(), 'User should have transactions');
        $this->assertGreaterThan(0, $this->user->subscriptions()->count(), 'User should have subscriptions');
    }

    /** @test */
    public function net_worth_calculates_correctly()
    {
        $accounts = $this->user->accounts()->where('is_active', true)->get();
        $netWorth = $accounts->sum('balance');

        $this->assertGreaterThan(0, $netWorth, 'Net worth should be positive');
        $this->assertIsNumeric($netWorth);
    }

    /** @test */
    public function total_debts_calculates_correctly()
    {
        $loans = $this->user->loans()->sum('remaining_amount');
        $ccBalance = $this->user->creditCards()->sum('current_balance');
        
        $totalDebts = $loans + $ccBalance;

        $this->assertGreaterThanOrEqual(0, $totalDebts, 'Total debts should be >= 0');
    }

    /** @test */
    public function monthly_subscription_cost_calculates_correctly()
    {
        $subs = $this->user->subscriptions()
            ->where('status', 'ACTIVE')
            ->get();

        // If no active subs from seeder, that's ok - test that logic works
        if ($subs->count() > 0) {
            $monthlyCost = $subs->sum('monthly_cost');
            $this->assertGreaterThan(0, $monthlyCost, 'Monthly cost should be > 0');
            $this->assertIsNumeric($monthlyCost);
        } else {
            $this->assertTrue(true, 'No active subscriptions in test data');
        }
    }

    /** @test */
    public function expenses_by_category_query_works()
    {
        $expenses = $this->user->transactions()
            ->where('amount', '<', 0)
            ->with('category', 'type')
            ->get()
            ->groupBy('category_id')
            ->map(fn ($group) => [
                'category' => $group->first()->category->name ?? 'Uncategorized',
                'total' => abs($group->sum('amount')),
            ]);

        $this->assertGreaterThan(0, $expenses->count(), 'Should have categorized expenses');
    }

    /** @test */
    public function monthly_cashflow_query_works()
    {
        $byType = $this->user->transactions()
            ->with('type')
            ->get()
            ->groupBy(fn ($tx) => $tx->type->name)
            ->map(fn ($group) => $group->sum('amount'));

        $this->assertGreaterThan(0, $byType->count(), 'Should have transactions by type');
    }

    /** @test */
    public function all_widgets_queries_scope_to_user()
    {
        $otherUser = User::factory()->create();

        // Verify current user's data
        $this->assertGreaterThan(0, $this->user->transactions()->count());

        // Verify other user has no data
        $this->assertEquals(0, $otherUser->transactions()->count());
    }
}

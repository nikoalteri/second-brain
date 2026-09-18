<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardNetWorthHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-04-23'));

        $this->user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'checking',
            'opening_balance' => 100,
            'balance' => 100,
            'created_at' => Carbon::parse('2025-06-01'),
        ]);

        $income = TransactionType::query()->firstOrCreate(['name' => 'Earnings'], ['is_income' => true]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $income->id,
            'amount' => 10,
            'date' => '2026-02-10',
        ]);

        Sanctum::actingAs($this->user);
    }

    private function trend(int $year, int $month): array
    {
        $points = $this->getJson("/api/v1/dashboard/charts?year={$year}&month={$month}")
            ->assertOk()
            ->json('data.net_worth_trend');

        return collect($points)->pluck('value', 'label')->all();
    }

    public function test_a_past_month_does_not_include_later_movements(): void
    {
        $trend = $this->trend(2026, 1);

        $this->assertSame(100.0, (float) $trend['Jan 2026']);
        $this->assertSame(100.0, (float) $trend['Dec 2025']);
    }

    public function test_points_reflect_the_movements_up_to_each_month(): void
    {
        $trend = $this->trend(2026, 4);

        $this->assertSame(100.0, (float) $trend['Jan 2026']);
        $this->assertSame(110.0, (float) $trend['Feb 2026']);
        $this->assertSame(110.0, (float) $trend['Apr 2026']);
    }

    public function test_the_same_month_gives_the_same_value_whatever_month_is_selected(): void
    {
        $this->assertSame(
            (float) $this->trend(2026, 4)['Jan 2026'],
            (float) $this->trend(2026, 2)['Jan 2026'],
        );
    }

    public function test_an_account_without_a_recorded_opening_balance_keeps_its_funds(): void
    {
        Account::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'savings',
            'opening_balance' => 0,
            'balance' => 500,
            'created_at' => Carbon::parse('2025-06-01'),
        ]);

        $trend = $this->trend(2026, 1);

        $this->assertSame(600.0, (float) $trend['Jan 2026']);
    }
}

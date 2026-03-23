<?php

namespace Tests\Unit;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;
    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubscriptionService::class);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function calculate_monthly_cost_from_annual_for_monthly_frequency()
    {
        $monthly = $this->service->calculateMonthlyCost(12.00, SubscriptionFrequency::MONTHLY);
        $this->assertEquals(12.00, $monthly);
    }

    /** @test */
    public function calculate_monthly_cost_from_annual_for_annual_frequency()
    {
        $monthly = $this->service->calculateMonthlyCost(120.00, SubscriptionFrequency::ANNUAL);
        $this->assertEquals(10.00, $monthly);
    }

    /** @test */
    public function calculate_monthly_cost_from_annual_for_biennial_frequency()
    {
        $monthly = $this->service->calculateMonthlyCost(240.00, SubscriptionFrequency::BIENNIAL);
        $this->assertEquals(10.00, $monthly);
    }

    /** @test */
    public function calculate_annual_cost_from_monthly_for_monthly_frequency()
    {
        $annual = $this->service->calculateAnnualCost(12.00, SubscriptionFrequency::MONTHLY);
        $this->assertEquals(12.00, $annual);
    }

    /** @test */
    public function calculate_annual_cost_from_monthly_for_annual_frequency()
    {
        $annual = $this->service->calculateAnnualCost(10.00, SubscriptionFrequency::ANNUAL);
        $this->assertEquals(120.00, $annual);
    }

    /** @test */
    public function calculate_annual_cost_from_monthly_for_biennial_frequency()
    {
        $annual = $this->service->calculateAnnualCost(10.00, SubscriptionFrequency::BIENNIAL);
        $this->assertEquals(240.00, $annual);
    }

    /** @test */
    public function get_monthly_total_for_user_subscriptions()
    {
        // Subscription 1: MONTHLY subscription with €10 monthly cost
        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Netflix',
            'frequency' => SubscriptionFrequency::MONTHLY,
            'monthly_cost' => 10.00,
            'annual_cost' => 120.00,  // calculated: 10 * 12
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        // Subscription 2: ANNUAL subscription with €120 annual cost (= €10/month)
        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Adobe',
            'frequency' => SubscriptionFrequency::ANNUAL,
            'monthly_cost' => 10.00,  // calculated: 120 / 12
            'annual_cost' => 120.00,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        // Subscription 3: BIENNIAL, INACTIVE (should not count)
        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'OnlyOffice',
            'frequency' => SubscriptionFrequency::BIENNIAL,
            'monthly_cost' => 10.00,  // calculated: 240 / 24
            'annual_cost' => 240.00,
            'status' => SubscriptionStatus::INACTIVE,
        ]);

        $total = $this->service->getMonthlyTotal($this->user->id);
        // Should be 10 + 10 = 20 (only active subscriptions, INACTIVE not counted)
        $this->assertEquals(20.00, $total);
    }

    /** @test */
    public function get_upcoming_renewals()
    {
        $now = Carbon::parse('2026-03-23');
        Carbon::setTestNow($now);

        // Create 3 subscriptions without events to control exact dates
        Subscription::factory()->state([
            'user_id' => $this->user->id,
            'next_renewal_date' => $now->copy()->addDays(3),
            'status' => SubscriptionStatus::ACTIVE,
        ])->make()->saveQuietly();

        Subscription::factory()->state([
            'user_id' => $this->user->id,
            'next_renewal_date' => $now->copy()->addDays(10),
            'status' => SubscriptionStatus::ACTIVE,
        ])->make()->saveQuietly();

        Subscription::factory()->state([
            'user_id' => $this->user->id,
            'next_renewal_date' => $now->copy()->subDays(5),
            'status' => SubscriptionStatus::ACTIVE,
        ])->make()->saveQuietly();

        $upcoming = $this->service->getUpcomingRenewals(7, $this->user->id);
        
        $this->assertEquals(1, $upcoming->count());
        $this->assertEquals($now->addDays(3)->toDateString(), $upcoming->first()->next_renewal_date->toDateString());
    }

    /** @test */
    public function calculate_next_renewal_date_for_monthly_subscription()
    {
        $subscription = Subscription::factory()->make([
            'frequency' => SubscriptionFrequency::MONTHLY,
            'day_of_month' => 15,
        ]);

        $from = Carbon::parse('2026-03-10');
        $next = $this->service->calculateNextRenewalDate($subscription, $from);

        $this->assertEquals('2026-04-15', $next->toDateString());
    }

    /** @test */
    public function calculate_next_renewal_date_for_annual_subscription()
    {
        $subscription = Subscription::factory()->make([
            'frequency' => SubscriptionFrequency::ANNUAL,
            'day_of_month' => 15,
        ]);

        $from = Carbon::parse('2026-03-10');
        $next = $this->service->calculateNextRenewalDate($subscription, $from);

        $this->assertEquals('2027-03-15', $next->toDateString());
    }

    /** @test */
    public function calculate_next_renewal_date_for_biennial_subscription()
    {
        $subscription = Subscription::factory()->make([
            'frequency' => SubscriptionFrequency::BIENNIAL,
            'day_of_month' => 15,
        ]);

        $from = Carbon::parse('2026-03-10');
        $next = $this->service->calculateNextRenewalDate($subscription, $from);

        $this->assertEquals('2028-03-15', $next->toDateString());
    }

    /** @test */
    public function observer_calculates_costs_on_creation()
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'frequency' => SubscriptionFrequency::ANNUAL,
            'monthly_cost' => 10.00,
            'annual_cost' => null,
        ]);

        $this->assertEquals(120.00, $subscription->annual_cost);
    }
}

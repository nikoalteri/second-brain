<?php

namespace Tests\Unit\Services;

use App\Jobs\SendUtilityAlert;
use App\Models\Property;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityBill;
use App\Services\UtilityAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class UtilityAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private UtilityAnalytics $service;
    private User $user;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UtilityAnalytics::class);
        $this->user = User::factory()->create();
        $this->property = Property::factory()->for($this->user)->create();
        Bus::fake();
    }

    #[Test]
    public function calculates_trends_with_monthly_aggregations()
    {
        $utility = Utility::factory()
            ->for($this->property)
            ->for($this->user)
            ->create();

        // Add bills for multiple months
        for ($i = 1; $i <= 12; $i++) {
            $utility->utilityBills()->create([
                'date' => now()->subMonths(12 - $i)->startOfMonth()->addDays(15),
                'cost' => 100 + ($i * 10),
                'reading' => 1000 + ($i * 50),
                'user_id' => $this->user->id,
            ]);
        }

        $trends = $this->service->calculateTrends($utility);

        $this->assertArrayHasKey('average_monthly', $trends);
        $this->assertArrayHasKey('total_cost', $trends);
        $this->assertArrayHasKey('trend', $trends);
        $this->assertCount(12, $trends['months']);
    }

    #[Test]
    public function calculates_average_monthly_cost()
    {
        $utility = Utility::factory()
            ->for($this->property)
            ->for($this->user)
            ->create();

        $utility->utilityBills()->create([
            'date' => now()->subMonths(1),
            'cost' => 100,
            'user_id' => $this->user->id,
        ]);
        $utility->utilityBills()->create([
            'date' => now(),
            'cost' => 200,
            'user_id' => $this->user->id,
        ]);

        $trends = $this->service->calculateTrends($utility);

        $this->assertEquals(150, $trends['average_monthly']);
        $this->assertEquals(300, $trends['total_cost']);
    }

    #[Test]
    public function determines_trend_increasing()
    {
        $utility = Utility::factory()
            ->for($this->property)
            ->for($this->user)
            ->create();

        // Create bills with increasing costs
        $utility->utilityBills()->create([
            'date' => now()->subMonths(5),
            'cost' => 50,
            'user_id' => $this->user->id,
        ]);
        $utility->utilityBills()->create([
            'date' => now()->subMonths(2),
            'cost' => 100,
            'user_id' => $this->user->id,
        ]);
        $utility->utilityBills()->create([
            'date' => now(),
            'cost' => 200,
            'user_id' => $this->user->id,
        ]);

        $trends = $this->service->calculateTrends($utility);

        $this->assertEquals('increasing', $trends['trend']);
    }

    #[Test]
    public function checks_alert_when_cost_exceeds_threshold()
    {
        $utility = Utility::factory()
            ->for($this->property)
            ->for($this->user)
            ->create();

        // Create historical average of 100
        for ($i = 1; $i <= 12; $i++) {
            $utility->utilityBills()->create([
                'date' => now()->subMonths(12 - $i)->startOfMonth(),
                'cost' => 100,
                'user_id' => $this->user->id,
            ]);
        }

        // Add current month bill that's 30% over average (should trigger alert)
        $utility->utilityBills()->create([
            'date' => now(),
            'cost' => 150,
            'user_id' => $this->user->id,
        ]);

        $alert = $this->service->checkAlert($utility);

        $this->assertTrue($alert);
        Bus::assertDispatched(SendUtilityAlert::class);
    }

    #[Test]
    public function does_not_alert_when_cost_under_threshold()
    {
        $utility = Utility::factory()
            ->for($this->property)
            ->for($this->user)
            ->create();

        // Create historical average of 100
        for ($i = 1; $i <= 12; $i++) {
            $utility->utilityBills()->create([
                'date' => now()->subMonths(12 - $i)->startOfMonth(),
                'cost' => 100,
                'user_id' => $this->user->id,
            ]);
        }

        // Add current month bill that's 20% over average (below 25% threshold)
        $utility->utilityBills()->create([
            'date' => now(),
            'cost' => 120,
            'user_id' => $this->user->id,
        ]);

        $alert = $this->service->checkAlert($utility);

        $this->assertFalse($alert);
        Bus::assertNotDispatched(SendUtilityAlert::class);
    }

    #[Test]
    public function gets_property_trends_for_all_utilities()
    {
        $utility1 = Utility::factory()
            ->for($this->property)
            ->for($this->user)
            ->create(['type' => 'electricity']);

        $utility2 = Utility::factory()
            ->for($this->property)
            ->for($this->user)
            ->create(['type' => 'gas']);

        $utility1->utilityBills()->create([
            'date' => now(),
            'cost' => 100,
            'user_id' => $this->user->id,
        ]);
        $utility2->utilityBills()->create([
            'date' => now(),
            'cost' => 75,
            'user_id' => $this->user->id,
        ]);

        $trends = $this->service->getPropertyTrends($this->property);

        $this->assertCount(2, $trends['utilities']);
        $this->assertEquals(175, $trends['total_cost']);
    }

    #[Test]
    public function gets_consumption_by_category()
    {
        $utility1 = Utility::factory()
            ->for($this->property)
            ->for($this->user)
            ->create(['type' => 'electricity']);

        $utility1->utilityBills()->create([
            'date' => now()->subMonths(6),
            'cost' => 100,
            'reading' => 1000,
            'user_id' => $this->user->id,
        ]);
        $utility1->utilityBills()->create([
            'date' => now(),
            'cost' => 150,
            'reading' => 1500,
            'user_id' => $this->user->id,
        ]);

        $consumption = $this->service->getConsumptionByCategory($this->property);

        $this->assertArrayHasKey('electricity', $consumption);
        $this->assertEquals(250, $consumption['electricity']['cost']);
        $this->assertEquals(2500, $consumption['electricity']['consumption']);
    }
}

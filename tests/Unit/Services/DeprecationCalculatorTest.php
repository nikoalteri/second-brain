<?php

namespace Tests\Unit\Services;

use App\Models\Inventory;
use App\Models\InventoryCategory;
use App\Models\Property;
use App\Models\User;
use App\Services\DeprecationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeprecationCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private DeprecationCalculator $calculator;
    private User $user;
    private Property $property;
    private InventoryCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(DeprecationCalculator::class);
        $this->user = User::factory()->create();
        $this->property = Property::factory()->for($this->user)->create();
        $this->category = InventoryCategory::factory()
            ->for($this->user)
            ->create(['depreciation_rate' => 10]);
    }

    #[Test]
    public function calculates_value_with_zero_years_returns_original()
    {
        $item = Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($this->category, 'category')
            ->create([
                'value' => 1000,
                'purchase_date' => now(),
            ]);

        $value = $this->calculator->calculateValue($item);

        // Should be very close to original since bought today
        $this->assertGreaterThan(990, $value);
    }

    #[Test]
    public function calculates_value_with_one_year_at_ten_percent()
    {
        $item = Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($this->category, 'category')
            ->create([
                'value' => 1000,
                'purchase_date' => now()->subYears(1),
            ]);

        $value = $this->calculator->calculateValue($item);

        // 1000 * (1 - 0.10)^1 = 900
        $this->assertGreaterThan(890, $value);
        $this->assertLessThan(910, $value);
    }

    #[Test]
    public function calculates_compound_depreciation()
    {
        $item = Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($this->category, 'category')
            ->create([
                'value' => 1000,
                'purchase_date' => now()->subYears(2),
            ]);

        $value = $this->calculator->calculateValue($item);

        // 1000 * (1 - 0.10)^2 = 810
        $this->assertGreaterThan(800, $value);
        $this->assertLessThan(820, $value);
    }

    #[Test]
    public function returns_original_value_without_purchase_date()
    {
        $item = Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($this->category, 'category')
            ->create([
                'value' => 1000,
                'purchase_date' => null,
            ]);

        $value = $this->calculator->calculateValue($item);

        $this->assertEquals(1000, $value);
    }

    #[Test]
    public function insurance_value_rounds_up_to_nearest_100()
    {
        $item = Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($this->category, 'category')
            ->create([
                'value' => 1000,
                'purchase_date' => now()->subYears(2),
            ]);

        $insuranceValue = $this->calculator->insuranceValue($item);

        // Current value ~810, should round up to 900
        $this->assertEquals(900, $insuranceValue);
    }

    #[Test]
    public function insurance_value_rounds_up_from_zero()
    {
        $item = Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($this->category, 'category')
            ->create([
                'value' => 10,
                'purchase_date' => now()->subYears(50), // Very old
            ]);

        $insuranceValue = $this->calculator->insuranceValue($item);

        // Should round up from near-zero to 100
        $this->assertGreaterThanOrEqual(100, $insuranceValue);
    }

    #[Test]
    public function gets_property_inventory_report()
    {
        $item1 = Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($this->category, 'category')
            ->create([
                'value' => 1000,
                'purchase_date' => now()->subYears(1),
            ]);

        $item2 = Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($this->category, 'category')
            ->create([
                'value' => 500,
                'purchase_date' => now(),
            ]);

        $report = $this->calculator->getPropertyInventoryReport($this->property);

        $this->assertEquals(1500, $report['total_original_value']);
        $this->assertGreater($report['total_original_value'], $report['total_current_value']);
        $this->assertGreater(0, $report['total_depreciation']);
        $this->assertCount(1, $report['by_category']);
        $this->assertEquals(2, $report['by_category'][0]['items']);
    }

    #[Test]
    public function groups_inventory_by_category()
    {
        $category1 = InventoryCategory::factory()
            ->for($this->user)
            ->create(['depreciation_rate' => 10]);

        $category2 = InventoryCategory::factory()
            ->for($this->user)
            ->create(['depreciation_rate' => 20]);

        Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($category1, 'category')
            ->create(['value' => 1000]);

        Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($category2, 'category')
            ->create(['value' => 500]);

        $report = $this->calculator->getPropertyInventoryReport($this->property);

        $this->assertCount(2, $report['by_category']);
    }

    #[Test]
    public function calculates_annual_depreciation()
    {
        $category = InventoryCategory::factory()
            ->for($this->user)
            ->create(['depreciation_rate' => 20]);

        Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($category, 'category')
            ->create(['value' => 1000]);

        $annualDepreciation = $this->calculator->getAnnualDepreciation($this->property);

        // 1000 * 0.20 = 200
        $this->assertEquals(200, $annualDepreciation);
    }

    #[Test]
    public function depreciation_never_goes_below_zero()
    {
        $item = Inventory::factory()
            ->for($this->property)
            ->for($this->user)
            ->for($this->category, 'category')
            ->create([
                'value' => 100,
                'purchase_date' => now()->subYears(100), // Very old
            ]);

        $value = $this->calculator->calculateValue($item);

        $this->assertGreaterThanOrEqual(0, $value);
    }
}

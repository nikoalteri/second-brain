<?php

namespace Tests\Unit\Services;

use App\Models\Property;
use App\Models\User;
use App\Services\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PropertyServiceTest extends TestCase
{
    use RefreshDatabase;

    private PropertyService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PropertyService::class);
        $this->user = User::factory()->create();
    }

    #[Test]
    public function creates_property_with_all_attributes()
    {
        $data = [
            'address' => '123 Main St',
            'property_type' => 'house',
            'lease_start_date' => '2023-01-01',
            'lease_end_date' => '2024-01-01',
            'estimated_value' => 500000,
        ];

        $property = $this->service->create($this->user, $data);

        $this->assertDatabaseHas('properties', [
            'user_id' => $this->user->id,
            'address' => '123 Main St',
            'property_type' => 'house',
            'estimated_value' => 500000,
        ]);
        $this->assertNotNull($property->id);
    }

    #[Test]
    public function validates_lease_date_range()
    {
        $this->expectException(ValidationException::class);

        $this->service->create($this->user, [
            'address' => '123 Main St',
            'property_type' => 'house',
            'lease_start_date' => '2024-01-01',
            'lease_end_date' => '2023-01-01', // End before start
            'estimated_value' => 100000,
        ]);
    }

    #[Test]
    public function updates_property_correctly()
    {
        $property = Property::factory()->for($this->user)->create([
            'address' => 'Old Address',
        ]);

        $updated = $this->service->update($property, [
            'address' => 'New Address',
            'estimated_value' => 600000,
        ]);

        $this->assertEquals('New Address', $updated->address);
        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'address' => 'New Address',
        ]);
    }

    #[Test]
    public function soft_deletes_property()
    {
        $property = Property::factory()->for($this->user)->create();
        $id = $property->id;

        $this->service->delete($property);

        $this->assertSoftDeleted('properties', ['id' => $id]);
    }

    #[Test]
    public function gets_property_metrics_with_aggregations()
    {
        $property = Property::factory()->for($this->user)->create();

        // Create maintenance task and record
        $task = $property->maintenanceTasks()->create([
            'name' => 'HVAC Service',
            'type' => 'preventive',
            'frequency' => 'annually',
            'user_id' => $this->user->id,
        ]);
        $task->propertyMaintenanceRecords()->create([
            'date' => now(),
            'cost' => 150,
            'user_id' => $this->user->id,
        ]);

        // Create utility
        $utility = $property->utilities()->create([
            'type' => 'electricity',
            'provider' => 'Electric Co',
            'billing_cycle' => 'monthly',
            'user_id' => $this->user->id,
        ]);
        $utility->utilityBills()->create([
            'date' => now(),
            'cost' => 120,
            'user_id' => $this->user->id,
        ]);

        // Create inventory
        $property->inventories()->create([
            'name' => 'TV',
            'value' => 1000,
            'location' => 'Living Room',
            'inventory_category_id' => 1,
            'user_id' => $this->user->id,
        ]);

        $metrics = $this->service->getPropertyWithMetrics($property);

        $this->assertEquals(1, $metrics['maintenance_count']);
        $this->assertEquals(1, $metrics['utilities']);
        $this->assertGreater($metrics['inventory_value'], 0);
        $this->assertEquals(270, $metrics['cost_ytd']); // 150 + 120
    }

    #[Test]
    public function returns_empty_metrics_for_new_property()
    {
        $property = Property::factory()->for($this->user)->create();

        $metrics = $this->service->getPropertyWithMetrics($property);

        $this->assertEquals(0, $metrics['maintenance_count']);
        $this->assertEquals(0, $metrics['utilities']);
        $this->assertEquals(0, $metrics['inventory_value']);
        $this->assertEquals(0, $metrics['cost_ytd']);
    }
}

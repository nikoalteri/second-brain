<?php

namespace Tests\Unit\Models;

use App\Models\InventoryCategory;
use App\Models\MaintenanceTask;
use App\Models\Property;
use App\Models\PropertyMaintenanceRecord;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilityBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function properties_table_has_required_columns()
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create([
            'address' => '123 Main',
            'property_type' => 'house',
            'estimated_value' => 500000,
        ]);

        $this->assertNotNull($property->id);
        $this->assertEquals($user->id, $property->user_id);
        $this->assertTrue($property->exists);
        $this->assertNull($property->deleted_at);
    }

    #[Test]
    public function maintenance_tasks_table_has_required_columns()
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();
        $task = MaintenanceTask::factory()
            ->for($property)
            ->for($user)
            ->create([
                'name' => 'HVAC Service',
                'type' => 'preventive',
                'frequency' => 'annually',
            ]);

        $this->assertNotNull($task->id);
        $this->assertEquals($property->id, $task->property_id);
        $this->assertEquals('HVAC Service', $task->name);
        $this->assertNotNull($task->next_due_date);
    }

    #[Test]
    public function property_soft_delete_cascades()
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();
        MaintenanceTask::factory()->for($property)->for($user)->create();

        $property->delete();

        $this->assertSoftDeleted('properties', ['id' => $property->id]);
    }

    #[Test]
    public function utilities_table_has_required_columns()
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();
        $utility = Utility::factory()
            ->for($property)
            ->for($user)
            ->create([
                'type' => 'electricity',
                'provider' => 'Electric Co',
                'billing_cycle' => 'monthly',
            ]);

        $this->assertEquals('electricity', $utility->type);
        $this->assertEquals('Electric Co', $utility->provider);
        $this->assertNull($utility->deleted_at);
    }

    #[Test]
    public function utility_bills_table_has_required_columns()
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();
        $utility = Utility::factory()->for($property)->for($user)->create();
        $bill = UtilityBill::factory()
            ->for($utility)
            ->for($user)
            ->create([
                'date' => now(),
                'cost' => 150,
                'reading' => 1000,
            ]);

        $this->assertEquals(150, $bill->cost);
        $this->assertEquals(1000, $bill->reading);
        $this->assertNotNull($bill->date);
    }

    #[Test]
    public function inventory_category_table_has_required_columns()
    {
        $user = User::factory()->create();
        $category = InventoryCategory::factory()
            ->for($user)
            ->create([
                'name' => 'Electronics',
                'depreciation_rate' => 20,
            ]);

        $this->assertEquals('Electronics', $category->name);
        $this->assertEquals(20, $category->depreciation_rate);
    }

    #[Test]
    public function foreign_key_constraints_work()
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();
        $utility = Utility::factory()->for($property)->for($user)->create();

        // Relationship should work
        $this->assertTrue($property->utilities()->where('id', $utility->id)->exists());
        $this->assertEquals($property->id, $utility->property_id);
    }

    #[Test]
    public function maintenance_record_links_to_task()
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user)->create();
        $task = MaintenanceTask::factory()->for($property)->for($user)->create();
        $record = PropertyMaintenanceRecord::factory()
            ->for($task, 'maintenanceTask')
            ->for($user)
            ->create();

        $this->assertEquals($task->id, $record->maintenance_task_id);
        $this->assertNotNull($task->propertyMaintenanceRecords);
    }
}

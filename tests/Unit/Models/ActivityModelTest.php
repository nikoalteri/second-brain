<?php

namespace Tests\Unit\Models;

use App\Models\Activity;
use App\Models\Itinerary;
use App\Models\User;
use App\Enums\ActivityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function activity_creates_with_type_from_enum()
    {
        $activity = Activity::factory()->create([
            'title' => 'Visit Eiffel Tower',
            'type' => ActivityType::SIGHTSEEING,
        ]);

        $this->assertNotNull($activity->id);
        $this->assertEquals('Visit Eiffel Tower', $activity->title);
        $this->assertEquals(ActivityType::SIGHTSEEING, $activity->type);
    }

    /** @test */
    public function activity_belongs_to_itinerary()
    {
        $itinerary = Itinerary::factory()->create();
        $activity = Activity::factory()->for($itinerary)->create();

        $this->assertEquals($itinerary->id, $activity->itinerary_id);
        $this->assertInstanceOf(Itinerary::class, $activity->itinerary);
    }

    /** @test */
    public function activity_belongs_to_user()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->for($user)->create();

        $this->assertEquals($user->id, $activity->user_id);
        $this->assertInstanceOf(User::class, $activity->user);
    }

    /** @test */
    public function activity_start_time_casts_to_datetime()
    {
        $activity = Activity::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $activity->start_time);
    }

    /** @test */
    public function activity_end_time_casts_to_datetime()
    {
        $activity = Activity::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $activity->end_time);
    }

    /** @test */
    public function activity_cost_casts_to_decimal()
    {
        $activity = Activity::factory()->create([
            'cost' => 123.45,
        ]);

        $this->assertIsNumeric($activity->cost);
        $this->assertEquals('123.45', (string) $activity->cost);
    }

    /** @test */
    public function activity_type_enum_casting()
    {
        $activity = Activity::factory()->create([
            'type' => ActivityType::DINING,
        ]);

        $this->assertInstanceOf(ActivityType::class, $activity->type);
        $this->assertEquals(ActivityType::DINING, $activity->type);
    }

    /** @test */
    public function activity_supports_all_activity_types()
    {
        foreach (ActivityType::cases() as $type) {
            $activity = Activity::factory()->create(['type' => $type]);
            $this->assertEquals($type, $activity->type);
        }
    }

    /** @test */
    public function activity_soft_deletes()
    {
        $activity = Activity::factory()->create();
        $activityId = $activity->id;

        $activity->delete();

        $this->assertNull(Activity::find($activityId));
        $this->assertNotNull(Activity::onlyTrashed()->find($activityId));
    }

    /** @test */
    public function activity_timestamps_exist()
    {
        $activity = Activity::factory()->create();

        $this->assertNotNull($activity->created_at);
        $this->assertNotNull($activity->updated_at);
    }
}

<?php

namespace Tests\Feature\Health;

use App\Models\BloodTest;
use App\Models\HealthRecord;
use App\Models\Medication;
use App\Models\MedicalRecord;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function user_can_create_health_record()
    {
        $data = [
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'weight' => 75.5,
            'height' => 180,
            'heart_rate' => 72,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'temperature' => 37.0,
            'notes' => 'Regular checkup',
        ];

        $record = HealthRecord::create($data);

        $this->assertDatabaseHas('health_records', [
            'user_id' => $this->user->id,
            'weight' => 75.5,
            'height' => 180,
        ]);
        $this->assertEquals($this->user->id, $record->user_id);
    }

    /** @test */
    public function user_health_records_scoped_to_user()
    {
        $otherUser = User::factory()->create();

        HealthRecord::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'weight' => 75.0,
        ]);

        HealthRecord::create([
            'user_id' => $otherUser->id,
            'date' => now()->toDateString(),
            'weight' => 80.0,
        ]);

        $userRecords = $this->user->healthRecords;
        $this->assertCount(1, $userRecords);
        $this->assertEquals(75.0, $userRecords->first()->weight);
    }

    /** @test */
    public function user_can_log_workout()
    {
        $data = [
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'type' => 'cardio',
            'exercise_name' => 'Running',
            'duration_minutes' => 45,
            'calories_burned' => 450,
            'distance_km' => 7.5,
            'intensity_level' => 7,
            'location' => 'Park',
        ];

        $workout = Workout::create($data);

        $this->assertDatabaseHas('workouts', [
            'user_id' => $this->user->id,
            'exercise_name' => 'Running',
            'duration_minutes' => 45,
        ]);
        $this->assertEquals('cardio', $workout->type);
    }

    /** @test */
    public function user_can_add_medical_record()
    {
        $data = [
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'type' => 'appointment',
            'description' => 'Annual checkup',
            'doctor_name' => 'Dr. Smith',
            'clinic_hospital' => 'City Hospital',
            'notes' => 'All tests normal',
        ];

        $record = MedicalRecord::create($data);

        $this->assertDatabaseHas('medical_records', [
            'user_id' => $this->user->id,
            'description' => 'Annual checkup',
        ]);
        $this->assertEquals('appointment', $record->type);
    }

    /** @test */
    public function user_can_add_medication()
    {
        $data = [
            'user_id' => $this->user->id,
            'name' => 'Aspirin',
            'dosage' => '500mg',
            'frequency' => 'twice daily',
            'start_date' => now()->toDateString(),
            'reason' => 'Pain relief',
            'doctor_name' => 'Dr. Smith',
        ];

        $medication = Medication::create($data);

        $this->assertDatabaseHas('medications', [
            'user_id' => $this->user->id,
            'name' => 'Aspirin',
        ]);
        $this->assertTrue($medication->isActive());
    }

    /** @test */
    public function medication_shows_inactive_after_end_date()
    {
        $medication = Medication::create([
            'user_id' => $this->user->id,
            'name' => 'Aspirin',
            'dosage' => '500mg',
            'frequency' => 'twice daily',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(1)->toDateString(),
            'reason' => 'Pain relief',
        ]);

        $this->assertFalse($medication->isActive());
    }

    /** @test */
    public function user_can_log_blood_test()
    {
        $data = [
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'hemoglobin' => 14.5,
            'hematocrit' => 43.0,
            'glucose' => 95.0,
            'cholesterol' => 180.0,
            'lab_name' => 'Central Lab',
        ];

        $test = BloodTest::create($data);

        $this->assertDatabaseHas('blood_tests', [
            'user_id' => $this->user->id,
            'hemoglobin' => 14.5,
        ]);
        $this->assertEquals(180.0, $test->cholesterol);
    }

    /** @test */
    public function health_records_have_user_relationships()
    {
        HealthRecord::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'weight' => 75.0,
        ]);

        Workout::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'exercise_name' => 'Running',
            'duration_minutes' => 30,
            'type' => 'cardio',
        ]);

        MedicalRecord::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'type' => 'appointment',
            'description' => 'Checkup',
        ]);

        Medication::create([
            'user_id' => $this->user->id,
            'name' => 'Medicine',
            'dosage' => '100mg',
            'frequency' => 'daily',
            'start_date' => now()->toDateString(),
        ]);

        BloodTest::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'glucose' => 95.0,
        ]);

        $this->assertCount(1, $this->user->healthRecords);
        $this->assertCount(1, $this->user->workouts);
        $this->assertCount(1, $this->user->medicalRecords);
        $this->assertCount(1, $this->user->medications);
        $this->assertCount(1, $this->user->bloodTests);
    }

    /** @test */
    public function health_records_can_be_soft_deleted()
    {
        $record = HealthRecord::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'weight' => 75.0,
        ]);

        $record->delete();

        $this->assertSoftDeleted('health_records', ['id' => $record->id]);
        $this->assertCount(0, $this->user->healthRecords);
    }

    /** @test */
    public function workout_types_are_valid()
    {
        $validTypes = ['cardio', 'strength', 'flexibility', 'sports', 'other'];

        foreach ($validTypes as $type) {
            $workout = Workout::create([
                'user_id' => $this->user->id,
                'date' => now()->toDateString(),
                'exercise_name' => 'Exercise',
                'duration_minutes' => 30,
                'type' => $type,
            ]);

            $this->assertEquals($type, $workout->type);
        }
    }

    /** @test */
    public function medical_record_types_are_valid()
    {
        $validTypes = ['appointment', 'diagnosis', 'vaccination', 'surgery', 'other'];

        foreach ($validTypes as $type) {
            $record = MedicalRecord::create([
                'user_id' => $this->user->id,
                'date' => now()->toDateString(),
                'type' => $type,
                'description' => 'Medical event',
            ]);

            $this->assertEquals($type, $record->type);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\BloodTest;
use App\Models\HealthRecord;
use App\Models\Medication;
use App\Models\MedicalRecord;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Seeder;

class HealthDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        // Health Records - Weekly for past 3 months
        for ($i = 0; $i < 12; $i++) {
            HealthRecord::create([
                'user_id' => $user->id,
                'date' => now()->subWeeks($i)->toDateString(),
                'weight' => 75 + rand(-3, 3) + (rand(0, 10) / 10),
                'height' => 180,
                'heart_rate' => 65 + rand(-5, 10),
                'blood_pressure_systolic' => 120 + rand(-10, 10),
                'blood_pressure_diastolic' => 80 + rand(-5, 5),
                'temperature' => 36.8 + (rand(-5, 5) / 10),
                'notes' => ['Regular', 'Good', 'Normal', 'Feeling well'][rand(0, 3)],
            ]);
        }

        // Workouts - 3-4 per week for past month
        $workoutTypes = ['cardio', 'strength', 'flexibility', 'sports'];
        $exercises = ['Running', 'Gym', 'Yoga', 'Swimming', 'Cycling', 'Dancing', 'Football'];
        
        for ($i = 0; $i < 15; $i++) {
            $type = $workoutTypes[rand(0, 3)];
            Workout::create([
                'user_id' => $user->id,
                'date' => now()->subDays(rand(1, 30))->toDateString(),
                'type' => $type,
                'exercise_name' => $exercises[rand(0, 6)],
                'duration_minutes' => 20 + rand(10, 90),
                'calories_burned' => rand(150, 600),
                'distance_km' => rand(2, 15),
                'intensity_level' => rand(3, 10),
                'location' => ['Park', 'Gym', 'Home', 'Beach', 'Track'][rand(0, 4)],
                'notes' => ['Great session', 'Felt good', 'Tired today', null][rand(0, 3)],
            ]);
        }

        // Medical Records - Past 6 months
        $medicalTypes = ['appointment', 'diagnosis', 'vaccination', 'surgery'];
        for ($i = 0; $i < 5; $i++) {
            MedicalRecord::create([
                'user_id' => $user->id,
                'date' => now()->subMonths(rand(1, 6))->toDateString(),
                'type' => $medicalTypes[rand(0, 3)],
                'doctor_name' => ['Dr. Smith', 'Dr. Johnson', 'Dr. Williams', 'Dr. Brown'][rand(0, 3)],
                'clinic_hospital' => ['City Hospital', 'Central Clinic', 'Medical Center', 'Health Plus'][rand(0, 3)],
                'description' => ['Annual checkup', 'Vaccination', 'Flu shot', 'Minor surgery', 'Consultation'][rand(0, 4)],
                'notes' => ['All normal', 'Follow up in 3 months', 'Prescribed medication', null][rand(0, 3)],
            ]);
        }

        // Medications - Currently active and past
        Medication::create([
            'user_id' => $user->id,
            'name' => 'Aspirin',
            'dosage' => '500mg',
            'frequency' => 'twice daily',
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => null,
            'reason' => 'Heart health',
            'doctor_name' => 'Dr. Smith',
            'notes' => 'Take with food',
        ]);

        Medication::create([
            'user_id' => $user->id,
            'name' => 'Vitamin D',
            'dosage' => '1000IU',
            'frequency' => 'once daily',
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => null,
            'reason' => 'Deficiency',
            'doctor_name' => 'Dr. Johnson',
            'notes' => 'Morning intake',
        ]);

        Medication::create([
            'user_id' => $user->id,
            'name' => 'Antibiotic',
            'dosage' => '250mg',
            'frequency' => 'three times daily',
            'start_date' => now()->subDays(14)->toDateString(),
            'end_date' => now()->subDays(7)->toDateString(),
            'reason' => 'Infection',
            'doctor_name' => 'Dr. Williams',
        ]);

        // Blood Tests - Quarterly
        for ($i = 0; $i < 4; $i++) {
            BloodTest::create([
                'user_id' => $user->id,
                'date' => now()->subMonths($i * 3)->toDateString(),
                'hemoglobin' => 14.0 + (rand(-2, 2) / 10),
                'hematocrit' => 42.0 + rand(-2, 2),
                'glucose' => 95 + rand(-10, 10),
                'cholesterol' => 180 + rand(-20, 30),
                'hdl' => 50 + rand(-5, 10),
                'ldl' => 110 + rand(-20, 20),
                'triglycerides' => 100 + rand(-30, 30),
                'white_blood_cells' => 6.5 + (rand(-2, 2) / 10),
                'red_blood_cells' => 4.5 + (rand(-5, 5) / 10),
                'platelets' => 220 + rand(-30, 30),
                'lab_name' => ['Central Lab', 'City Lab', 'Health Labs', 'Medical Testing'][rand(0, 3)],
                'notes' => ['All normal', 'Slightly high glucose', 'Follow up', null][rand(0, 3)],
            ]);
        }
    }
}

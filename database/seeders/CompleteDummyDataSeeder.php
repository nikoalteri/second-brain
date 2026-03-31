<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Message;
use App\Models\Contact;
use App\Models\Workout;
use App\Models\Habit;
use App\Models\Goal;
use App\Models\Project;
use App\Models\JournalEntry;
use App\Models\Note;
use App\Models\Recipe;
use App\Models\Meal;
use App\Models\Trip;
use App\Models\Flight;
use App\Models\Hotel;
use App\Models\Medication;
use App\Models\BloodTest;
use App\Models\Vehicle;
use App\Models\MaintenanceRecord;
use App\Models\Document;
use Illuminate\Database\Seeder;

class CompleteDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        // Messages - 10 records
        $messageData = [
            ['subject' => 'Project Update', 'importance' => 'high', 'category' => 'work'],
            ['subject' => 'Meeting Tomorrow', 'importance' => 'high', 'category' => 'work'],
            ['subject' => 'Code Review', 'importance' => 'medium', 'category' => 'work'],
            ['subject' => 'Lunch Plans', 'importance' => 'low', 'category' => 'personal'],
            ['subject' => 'Bug Report', 'importance' => 'high', 'category' => 'urgent'],
            ['subject' => 'Feature Request', 'importance' => 'medium', 'category' => 'work'],
            ['subject' => 'Team Update', 'importance' => 'medium', 'category' => 'work'],
            ['subject' => 'Birthday Reminder', 'importance' => 'low', 'category' => 'personal'],
            ['subject' => 'Doctor Appointment', 'importance' => 'high', 'category' => 'personal'],
            ['subject' => 'Payment Received', 'importance' => 'high', 'category' => 'work'],
        ];

        foreach ($messageData as $index => $data) {
            Message::create([
                'user_id' => $user->id,
                'subject' => $data['subject'],
                'content' => "Details about: {$data['subject']}",
                'importance' => $data['importance'],
                'category' => $data['category'],
                'read_at' => $index < 5 ? now()->subDays(rand(1, 30)) : null,
            ]);
        }

        // Contacts - 10 records
        $contactNames = [
            'John Smith', 'Jane Doe', 'Bob Johnson', 'Alice Brown', 'Charlie Wilson',
            'Diana Prince', 'Edward Norton', 'Fiona Apple', 'George Lucas', 'Hannah Montana',
        ];

        foreach ($contactNames as $index => $name) {
            Contact::create([
                'user_id' => $user->id,
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
                'phone' => '555-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'relationship_type' => ['family', 'friend', 'colleague', 'business'][$index % 4],
                'birthday' => now()->subYears(rand(25, 70))->toDateString(),
                'notes' => "Contact notes for $name.",
            ]);
        }

        // Workouts - 15 records
        $workoutTypes = ['running', 'cycling', 'swimming', 'weight_training', 'yoga', 'pilates', 'walking', 'hiking'];
        $workoutNames = [
            'Morning Run', 'Evening Jog', 'Gym Session', 'Yoga Class', 'Swimming', 'Cycling Tour', 'HIIT Training',
            'Weight Lifting', 'Pilates', 'Hiking', 'Basketball', 'Tennis', 'Dance Class', 'Boxing', 'Stretching',
        ];

        foreach ($workoutNames as $index => $name) {
            Workout::create([
                'user_id' => $user->id,
                'type' => $workoutTypes[$index % count($workoutTypes)],
                'date' => now()->subDays(rand(1, 90))->toDateString(),
                'duration_minutes' => rand(20, 120),
                'calories_burned' => rand(100, 800),
                'exercise_name' => $name,
                'distance_km' => rand(1, 50) / 2,
                'intensity_level' => rand(1, 5),
                'location' => 'Gym ' . (rand(1, 3)),
            ]);
        }

        // Habits - 10 records
        $habitNames = [
            'Morning Meditation', 'Read Daily', 'Exercise', 'Drink Water',
            'Journaling', 'Healthy Eating', 'Sleep Early', 'Study', 'Practice Guitar', 'Networking',
        ];
        $frequencies = ['daily', 'weekly', 'monthly'];

        foreach ($habitNames as $index => $name) {
            Habit::create([
                'user_id' => $user->id,
                'name' => $name,
                'frequency' => $frequencies[$index % 3],
                'start_date' => now()->subDays(rand(30, 365))->toDateString(),
            ]);
        }

        // Goals - 10 records
        $goalCategories = ['health', 'career', 'finance', 'personal', 'relationship', 'other'];
        $goalTitles = [
            'Lose 10 pounds', 'Learn Spanish', 'Read 12 books', 'Save \$5000', 'Build Project',
            'Run a Marathon', 'Improve Sleep', 'Double Income', 'Travel Abroad', 'Master Python',
        ];

        foreach ($goalTitles as $index => $title) {
            Goal::create([
                'user_id' => $user->id,
                'title' => $title,
                'category' => $goalCategories[$index % count($goalCategories)],
                'start_date' => now()->subDays(rand(30, 180))->toDateString(),
                'target_date' => now()->addDays(rand(30, 365))->toDateString(),
                'status' => 'in_progress',
                'target_value' => rand(50, 1000),
                'current_value' => rand(10, 500),
            ]);
        }

        // Projects - 8 records
        $projectStatuses = ['planning', 'in_progress', 'completed', 'cancelled'];
        $projectNames = [
            'Website Redesign', 'Mobile App', 'Data Migration', 'API Development',
            'Dashboard Analytics', 'Security Audit', 'Documentation', 'Performance',
        ];

        foreach ($projectNames as $index => $name) {
            Project::create([
                'user_id' => $user->id,
                'name' => $name,
                'description' => "Project: $name",
                'status' => $projectStatuses[$index % count($projectStatuses)],
                'start_date' => now()->subDays(rand(30, 200))->toDateString(),
                'due_date' => now()->addDays(rand(30, 200))->toDateString(),
            ]);
        }

        // Journal Entries - 20 records
        $moods = ['poor', 'fair', 'good', 'excellent'];
        for ($i = 0; $i < 20; $i++) {
            JournalEntry::create([
                'user_id' => $user->id,
                'title' => 'Day ' . ($i + 1),
                'content' => "Today was interesting. I accomplished several tasks and learned valuable lessons.",
                'mood' => $moods[$i % 4],
                'date' => now()->subDays(rand(0, 90))->toDateString(),
            ]);
        }

        // Notes - 12 records
        $noteColors = ['yellow', 'blue', 'green', 'red', 'purple', 'pink'];
        $noteTitles = [
            'Important Reminders', 'Meeting Notes', 'Ideas', 'Bugs', 'Features',
            'Client Feedback', 'To-Do List', 'Research', 'Quotes', 'Learning',
            'Thoughts', 'Tips',
        ];

        foreach ($noteTitles as $index => $title) {
            Note::create([
                'user_id' => $user->id,
                'title' => $title,
                'content' => "Note: $title",
                'color' => $noteColors[$index % count($noteColors)],
                'is_pinned' => $index < 3,
            ]);
        }

        // Recipes - 10 records
        $cuisines = ['italian', 'french', 'asian', 'american', 'mexican', 'spanish', 'greek', 'other'];
        $recipeNames = [
            'Pasta Carbonara', 'Chicken Stir Fry', 'Sushi Rolls', 'Pizza',
            'Tacos', 'Moussaka', 'Paella', 'Ramen', 'Curry', 'Risotto',
        ];

        foreach ($recipeNames as $index => $name) {
            Recipe::create([
                'user_id' => $user->id,
                'name' => $name,
                'cuisine' => $cuisines[$index % count($cuisines)],
                'prep_time_minutes' => rand(15, 45),
                'cook_time_minutes' => rand(20, 120),
                'servings' => rand(2, 8),
                'instructions' => "Recipe for $name.",
            ]);
        }

        // Meals - 15 records
        $mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
        for ($i = 0; $i < 15; $i++) {
            Meal::create([
                'user_id' => $user->id,
                'meal_type' => $mealTypes[$i % 4],
                'date_eaten' => now()->subDays(rand(0, 30))->toDateString(),
                'calories' => rand(300, 1200),
                'is_favorite' => $i % 3 == 0,
            ]);
        }

        // Trips - 5 records
        $tripStatuses = ['planning', 'in_progress', 'completed', 'cancelled'];
        $destinations = ['Paris', 'Tokyo', 'New York', 'Barcelona', 'Rome'];

        foreach ($destinations as $index => $destination) {
            Trip::create([
                'user_id' => $user->id,
                'destination' => $destination,
                'start_date' => now()->addDays(rand(30, 365))->toDateString(),
                'end_date' => now()->addDays(rand(40, 375))->toDateString(),
                'status' => $tripStatuses[$index % count($tripStatuses)],
                'budget' => rand(1000, 10000),
            ]);
        }

        // Flights - 5 records
        $trips = Trip::where('user_id', $user->id)->get();
        $bookingStatuses = ['booked', 'cancelled', 'completed'];
        $airlines = ['United', 'American', 'Delta', 'Southwest', 'British Airways'];

        for ($i = 0; $i < 5 && $i < $trips->count(); $i++) {
            Flight::create([
                'user_id' => $user->id,
                'trip_id' => $trips[$i]->id,
                'airline' => $airlines[$i],
                'flight_number' => 'AA' . str_pad(rand(100, 9999), 4, '0', STR_PAD_LEFT),
                'departure_time' => now()->addDays(rand(30, 360))->setHour(8)->setMinute(0),
                'arrival_time' => now()->addDays(rand(30, 360))->setHour(14)->setMinute(0),
                'departure_airport' => 'JFK',
                'arrival_airport' => 'CDG',
                'price' => rand(300, 1200),
                'status' => $bookingStatuses[$i % 3],
            ]);
        }

        // Hotels - 5 records
        for ($i = 0; $i < 5 && $i < $trips->count(); $i++) {
            Hotel::create([
                'user_id' => $user->id,
                'trip_id' => $trips[$i]->id,
                'name' => 'Hotel ' . ($i + 1),
                'location' => $trips[$i]->destination,
                'check_in_date' => $trips[$i]->start_date,
                'check_out_date' => $trips[$i]->end_date,
                'number_of_rooms' => rand(1, 3),
                'price_per_night' => rand(80, 400),
                'status' => $bookingStatuses[$i % 3],
            ]);
        }

        // Medications - 8 records
        $dosageUnits = ['mg', 'ml', 'tablet', 'capsule', 'drops', 'patch'];
        $medicationNames = [
            'Aspirin', 'Vitamin D', 'Ibuprofen', 'Amoxicillin',
            'Lisinopril', 'Metformin', 'Levothyroxine', 'Atorvastatin',
        ];

        foreach ($medicationNames as $index => $name) {
            Medication::create([
                'user_id' => $user->id,
                'name' => $name,
                'dosage' => rand(250, 1000),
                'dosage_unit' => $dosageUnits[$index % count($dosageUnits)],
                'frequency' => $index % 3 == 0 ? 'Once daily' : 'Twice daily',
                'start_date' => now()->subDays(rand(30, 365))->toDateString(),
                'end_date' => now()->addDays(rand(30, 365))->toDateString(),
            ]);
        }

        // Blood Tests - 10 records
        $resultStatuses = ['normal', 'low', 'high'];
        $testNames = [
            'Hemoglobin', 'Red Blood Cells', 'White Blood Cells', 'Platelets',
            'Cholesterol', 'Glucose', 'Liver', 'Kidney', 'Thyroid', 'PSA',
        ];

        foreach ($testNames as $index => $name) {
            BloodTest::create([
                'user_id' => $user->id,
                'test_name' => $name,
                'test_date' => now()->subDays(rand(1, 180))->toDateString(),
                'result_value' => rand(50, 300) + (rand(0, 100) / 100),
                'normal_range' => '100-200 mg/dL',
                'result_status' => $resultStatuses[$index % 3],
            ]);
        }

        // Vehicles - 3 records
        $vehicleTypes = ['car', 'motorcycle', 'bicycle', 'truck', 'van'];
        $vehicleModels = ['Toyota Camry', 'Honda Civic', 'BMW 320i'];

        foreach ($vehicleModels as $index => $model) {
            Vehicle::create([
                'user_id' => $user->id,
                'make' => explode(' ', $model)[0],
                'model' => $model,
                'year' => 2020 + $index,
                'type' => $vehicleTypes[$index % count($vehicleTypes)],
                'license_plate' => 'ABC' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'purchase_date' => now()->subYears(2 + $index)->toDateString(),
                'purchase_price' => rand(20000, 60000),
            ]);
        }

        // Maintenance Records - 8 records
        $vehicles = Vehicle::where('user_id', $user->id)->get();
        $maintenanceTypes = ['oil_change', 'repair', 'inspection', 'tire_service', 'battery_service', 'other'];

        for ($i = 0; $i < 8; $i++) {
            MaintenanceRecord::create([
                'user_id' => $user->id,
                'vehicle_id' => $vehicles[$i % $vehicles->count()]->id,
                'type' => $maintenanceTypes[$i % count($maintenanceTypes)],
                'maintenance_date' => now()->subDays(rand(0, 180))->toDateString(),
                'cost' => rand(50, 500),
                'mileage' => rand(10000, 150000),
            ]);
        }

        // Documents - 10 records
        $documentTypes = ['receipt', 'invoice', 'report', 'certificate', 'insurance', 'other'];
        $documentTitles = [
            'Tax Return', 'Insurance Policy', 'Car Registration', 'Mortgage Deed',
            'Utility Bill', 'Medical Report', 'Vaccine Certificate', 'Birth Certificate',
            'Passport', 'Driver License',
        ];

        foreach ($documentTitles as $index => $title) {
            Document::create([
                'user_id' => $user->id,
                'title' => $title,
                'type' => $documentTypes[$index % count($documentTypes)],
                'date_created' => now()->subDays(rand(0, 365))->toDateString(),
                'file_path' => 'documents/document_' . ($index + 1) . '.pdf',
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Message;
use App\Models\Event;
use App\Models\Document;
use App\Models\Vehicle;
use App\Models\MaintenanceRecord;
use App\Models\Recipe;
use App\Models\Meal;
use App\Models\Ingredient;
use App\Models\Trip;
use App\Models\Flight;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Seeder;

class Phase8DataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        // Contacts - 8 records
        $relationshipTypes = ['family', 'friend', 'colleague', 'business'];
        for ($i = 0; $i < 8; $i++) {
            Contact::create([
                'user_id' => $user->id,
                'name' => ['John Doe', 'Jane Smith', 'Michael Johnson', 'Sarah Williams', 'David Brown', 'Emily Davis', 'James Wilson', 'Lisa Anderson'][$i],
                'email' => "contact{$i}@example.com",
                'phone' => '555-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'relationship_type' => $relationshipTypes[$i % 4],
                'notes' => ['Close friend', 'Work colleague', 'Family member', 'Business partner', 'Old friend', 'New contact', 'Professional', 'Childhood friend'][$i],
                'birthday' => now()->subYears(rand(20, 60))->toDateString(),
            ]);
        }

        // Messages - 8 records
        $importances = ['low', 'medium', 'high'];
        $categories = ['personal', 'work', 'urgent'];
        for ($i = 0; $i < 8; $i++) {
            Message::create([
                'user_id' => $user->id,
                'subject' => ['Meeting Tomorrow', 'Project Update', 'Birthday Reminder', 'Lunch Plans', 'Urgent Issue', 'Feedback', 'Follow-up', 'Question'][$i],
                'content' => 'This is message content for item ' . ($i + 1),
                'importance' => $importances[$i % 3],
                'category' => $categories[$i % 3],
                'read_at' => $i < 3 ? now()->subDays(rand(1, 10)) : null,
            ]);
        }

        // Events - 8 records
        $eventTypes = ['meeting', 'birthday', 'anniversary', 'other'];
        for ($i = 0; $i < 8; $i++) {
            Event::create([
                'user_id' => $user->id,
                'title' => ['Conference 2024', 'Team Standup', 'Birthday Party', 'Anniversary', 'Product Launch', 'Client Meeting', 'Training Session', 'Team Outing'][$i],
                'description' => 'Event description for item ' . ($i + 1),
                'event_date' => now()->addDays(rand(1, 60))->toDateTimeString(),
                'event_type' => $eventTypes[$i % 4],
                'location' => ['New York', 'San Francisco', 'Chicago', 'Boston', 'Seattle', 'Austin', 'Denver', 'Miami'][$i],
                'attendees_count' => rand(5, 200),
            ]);
        }

        // Documents - 8 records (4 related to vehicles)
        $docTypes = ['title', 'insurance', 'registration', 'maintenance', 'other'];
        for ($i = 0; $i < 8; $i++) {
            Document::create([
                'user_id' => $user->id,
                'title' => ['Car Title', 'Insurance Policy', 'Vehicle Registration', 'Service Records', 'License', 'Passport', 'Birth Certificate', 'Warranty'][$i],
                'document_type' => $docTypes[$i % 5],
                'upload_path' => "documents/doc_{$i}.pdf",
                'upload_date' => now()->subDays(rand(1, 365))->toDateString(),
            ]);
        }

        // Vehicles - 3 records
        $vehicleTypes = ['car', 'motorcycle', 'truck', 'bicycle'];
        $vehicles = [];
        for ($i = 0; $i < 3; $i++) {
            $vehicle = Vehicle::create([
                'user_id' => $user->id,
                'make' => ['Toyota', 'Honda', 'Ford', 'BMW', 'Tesla', 'Audi'][$i],
                'model' => ['Camry', 'Civic', 'Focus', 'X5', 'Model 3', 'A4'][$i],
                'year' => now()->year - rand(1, 10),
                'license_plate' => 'LP' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'vehicle_type' => $vehicleTypes[$i % 4],
                'status' => rand(0, 1) ? 'active' : 'inactive',
                'notes' => ['Daily driver', 'Weekend car', 'Work truck'][$i],
            ]);
            $vehicles[] = $vehicle;
        }

        // MaintenanceRecords - 12 records (4 per vehicle)
        $serviceTypes = ['Oil Change', 'Tire Rotation', 'Brake Pad Replacement', 'Filter Change', 'Battery Check', 'Alignment'];
        for ($v = 0; $v < 3; $v++) {
            for ($i = 0; $i < 4; $i++) {
                MaintenanceRecord::create([
                    'user_id' => $user->id,
                    'vehicle_id' => $vehicles[$v]->id,
                    'service_type' => $serviceTypes[rand(0, 5)],
                    'date' => now()->subMonths(rand(1, 12))->toDateString(),
                    'cost' => rand(50, 500),
                    'description' => 'Routine maintenance',
                    'mileage' => rand(10000, 200000),
                ]);
            }
        }

        // Recipes - 8 records
        $cuisines = ['italian', 'asian', 'mexican', 'mediterranean', 'other'];
        $difficulties = ['easy', 'medium', 'hard'];
        for ($i = 0; $i < 8; $i++) {
            Recipe::create([
                'user_id' => $user->id,
                'name' => ['Pasta Carbonara', 'Sushi Rolls', 'Tacos Al Pastor', 'Greek Salad', 'Kung Pao Chicken', 'Risotto', 'Falafel', 'Paella'][$i],
                'cuisine' => $cuisines[$i % 5],
                'difficulty' => $difficulties[$i % 3],
                'prep_time' => rand(5, 60),
                'cook_time' => rand(10, 120),
                'servings' => rand(1, 8),
                'ingredients_list' => [
                    ['pasta', 'eggs', 'bacon', 'parmesan'],
                    ['rice', 'nori', 'fish', 'vegetables'],
                    ['tortillas', 'pork', 'onions', 'cilantro'],
                    ['tomatoes', 'cucumber', 'feta', 'olives'],
                    ['chicken', 'peanuts', 'soy sauce', 'peppers'],
                    ['rice', 'broth', 'cheese', 'butter'],
                    ['chickpeas', 'herbs', 'tahini', 'lemon'],
                    ['rice', 'saffron', 'seafood', 'peppers'],
                ][$i],
            ]);
        }

        // Meals - 16 records (2 per recipe for first 8 recipes)
        $recipes = Recipe::where('user_id', $user->id)->get();
        $mealCount = 0;
        foreach ($recipes as $recipe) {
            for ($i = 0; $i < 2; $i++) {
                Meal::create([
                    'user_id' => $user->id,
                    'recipe_id' => $recipe->id,
                    'date_eaten' => now()->subDays(rand(1, 60))->toDateString(),
                    'rating' => rand(2, 5),
                    'notes' => ['Delicious', 'Good', 'Amazing', 'Tasty', 'Needs improvement', 'Perfect', 'Very good'][$mealCount % 7],
                    'is_favorite' => rand(0, 1) ? true : false,
                ]);
                $mealCount++;
            }
        }

        // Ingredients - 12 records
        $categories = ['vegetable', 'meat', 'grain', 'dairy', 'spice', 'other'];
        $units = ['g', 'ml', 'tbsp', 'cup', 'piece'];
        for ($i = 0; $i < 12; $i++) {
            Ingredient::create([
                'user_id' => $user->id,
                'name' => ['Tomato', 'Chicken', 'Rice', 'Cheese', 'Garlic', 'Onion', 'Pepper', 'Salt', 'Olive Oil', 'Pasta', 'Mushroom', 'Carrot'][$i],
                'unit' => $units[rand(0, 4)],
                'category' => $categories[$i % 6],
            ]);
        }

        // Trips - 4 records
        $tripTypes = ['vacation', 'business', 'adventure'];
        $statuses = ['planned', 'in_progress', 'completed'];
        $trips = [];
        for ($i = 0; $i < 4; $i++) {
            $startDate = now()->addMonths(rand(-6, 6));
            $endDate = $startDate->clone()->addDays(rand(3, 14));
            $trip = Trip::create([
                'user_id' => $user->id,
                'destination' => ['Paris', 'Tokyo', 'New York', 'Barcelona'][$i],
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'trip_type' => $tripTypes[$i % 3],
                'status' => $statuses[$i % 3],
                'budget' => rand(1000, 10000),
                'total_spent' => rand(1000, 10000),
            ]);
            $trips[] = $trip;
        }

        // Flights - 6 records (1-2 per trip)
        $airlines = ['United Airlines', 'American Airlines', 'Delta', 'Southwest', 'British Airways', 'Lufthansa'];
        $flightCount = 0;
        foreach ($trips as $trip) {
            $numFlights = rand(1, 2);
            for ($i = 0; $i < $numFlights; $i++) {
                $depDate = now()->addDays(rand(1, 30));
                Flight::create([
                    'user_id' => $user->id,
                    'trip_id' => $trip->id,
                    'airline' => $airlines[$flightCount % 6],
                    'flight_number' => strtoupper(substr($airlines[$flightCount % 6], 0, 2)) . rand(100, 999),
                    'departure_date' => $depDate->toDateString(),
                    'departure_time' => rand(6, 22) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
                    'arrival_date' => $depDate->clone()->addHours(rand(1, 24))->toDateString(),
                    'arrival_time' => rand(6, 22) . ':' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
                    'departure_airport' => ['LAX', 'JFK', 'ORD', 'DFW', 'DEN', 'SFO'][$flightCount % 6],
                    'arrival_airport' => ['CDG', 'NRT', 'LHR', 'BCN', 'FCO', 'MAD'][$flightCount % 6],
                    'seat' => rand(1, 30) . ['A', 'B', 'C', 'D', 'E', 'F'][rand(0, 5)],
                ]);
                $flightCount++;
            }
        }

        // Hotels - 8 records (2 per trip)
        $hotelNames = ['The Ritz', 'Hilton', 'Marriott', 'Four Seasons', 'Hyatt', 'InterContinental', 'Sheraton', 'Crowne Plaza'];
        $hotelCount = 0;
        foreach ($trips as $trip) {
            for ($i = 0; $i < 2; $i++) {
                $checkIn = now()->addDays(rand(1, 30));
                $nights = rand(2, 7);
                Hotel::create([
                    'user_id' => $user->id,
                    'trip_id' => $trip->id,
                    'name' => $hotelNames[$hotelCount % 8],
                    'city' => ['Paris', 'Tokyo', 'New York', 'Barcelona'][$hotelCount % 4],
                    'check_in_date' => $checkIn->toDateString(),
                    'check_out_date' => $checkIn->clone()->addDays($nights)->toDateString(),
                    'nights' => $nights,
                    'cost_per_night' => rand(100, 500),
                    'total_cost' => $nights * rand(100, 500),
                ]);
                $hotelCount++;
            }
        }
    }
}

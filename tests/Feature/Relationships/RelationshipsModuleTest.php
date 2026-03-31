<?php

namespace Tests\Feature\Relationships;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // Contact Tests
    /** @test */
    public function user_can_create_contact()
    {
        $data = [
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'relationship_type' => 'friend',
            'notes' => 'College friend',
            'birthday' => '1990-01-15',
        ];

        $contact = Contact::create($data);

        $this->assertDatabaseHas('contacts', [
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $this->assertEquals('friend', $contact->relationship_type);
    }

    /** @test */
    public function contact_has_user_scoping()
    {
        Contact::create([
            'user_id' => $this->user->id,
            'name' => 'Contact 1',
            'relationship_type' => 'friend',
        ]);

        $otherUser = User::factory()->create();
        Contact::create([
            'user_id' => $otherUser->id,
            'name' => 'Contact 2',
            'relationship_type' => 'colleague',
        ]);

        $this->assertEquals(1, $this->user->contacts()->count());
    }

    /** @test */
    public function contact_can_be_soft_deleted()
    {
        $contact = Contact::create([
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'relationship_type' => 'family',
        ]);

        $contact->delete();

        $this->assertSoftDeleted($contact);
        $this->assertEquals(0, Contact::count());
        $this->assertEquals(1, Contact::withTrashed()->count());
    }

    /** @test */
    public function contact_validates_relationship_types()
    {
        $validTypes = ['family', 'friend', 'colleague', 'business'];

        foreach ($validTypes as $type) {
            $contact = Contact::create([
                'user_id' => $this->user->id,
                'name' => "Contact {$type}",
                'relationship_type' => $type,
            ]);
            $this->assertEquals($type, $contact->relationship_type);
        }
    }

    // Message Tests
    /** @test */
    public function user_can_create_message()
    {
        $data = [
            'user_id' => $this->user->id,
            'subject' => 'Test Message',
            'content' => 'This is a test message',
            'importance' => 'high',
            'category' => 'work',
        ];

        $message = Message::create($data);

        $this->assertDatabaseHas('messages', [
            'user_id' => $this->user->id,
            'subject' => 'Test Message',
            'importance' => 'high',
        ]);
    }

    /** @test */
    public function message_can_have_recipient()
    {
        $recipient = User::factory()->create();

        $message = Message::create([
            'user_id' => $this->user->id,
            'to_user_id' => $recipient->id,
            'subject' => 'Hello',
            'content' => 'Hi there',
            'category' => 'personal',
        ]);

        $this->assertEquals($recipient->id, $message->toUser->id);
    }

    /** @test */
    public function message_can_be_marked_as_read()
    {
        $message = Message::create([
            'user_id' => $this->user->id,
            'subject' => 'Test',
            'content' => 'Message',
            'category' => 'personal',
            'read_at' => null,
        ]);

        $this->assertNull($message->read_at);

        $message->update(['read_at' => now()]);
        $this->assertNotNull($message->fresh()->read_at);
    }

    /** @test */
    public function message_validates_importance_levels()
    {
        $validImportances = ['low', 'medium', 'high'];

        foreach ($validImportances as $importance) {
            $message = Message::create([
                'user_id' => $this->user->id,
                'subject' => "Message {$importance}",
                'content' => 'Content',
                'category' => 'personal',
                'importance' => $importance,
            ]);
            $this->assertEquals($importance, $message->importance);
        }
    }

    // Event Tests
    /** @test */
    public function user_can_create_event()
    {
        $data = [
            'user_id' => $this->user->id,
            'title' => 'Conference',
            'description' => 'Annual tech conference',
            'event_date' => now()->addMonth()->toDateTimeString(),
            'event_type' => 'meeting',
            'location' => 'New York',
            'attendees_count' => 150,
        ];

        $event = Event::create($data);

        $this->assertDatabaseHas('events', [
            'user_id' => $this->user->id,
            'title' => 'Conference',
        ]);
        $this->assertEquals(150, $event->attendees_count);
    }

    /** @test */
    public function event_validates_event_types()
    {
        $validTypes = ['meeting', 'birthday', 'anniversary', 'other'];

        foreach ($validTypes as $type) {
            $event = Event::create([
                'user_id' => $this->user->id,
                'title' => "Event {$type}",
                'event_date' => now()->toDateTimeString(),
                'event_type' => $type,
            ]);
            $this->assertEquals($type, $event->event_type);
        }
    }

    /** @test */
    public function event_can_be_soft_deleted()
    {
        $event = Event::create([
            'user_id' => $this->user->id,
            'title' => 'Birthday',
            'event_date' => now()->toDateTimeString(),
            'event_type' => 'birthday',
        ]);

        $event->delete();

        $this->assertSoftDeleted($event);
    }

    // Document Tests
    /** @test */
    public function user_can_create_document()
    {
        $data = [
            'user_id' => $this->user->id,
            'title' => 'Car Insurance',
            'document_type' => 'insurance',
            'upload_path' => 'documents/insurance_2024.pdf',
            'upload_date' => now()->toDateString(),
        ];

        $document = Document::create($data);

        $this->assertDatabaseHas('documents', [
            'user_id' => $this->user->id,
            'title' => 'Car Insurance',
            'document_type' => 'insurance',
        ]);
    }

    /** @test */
    public function document_validates_types()
    {
        $validTypes = ['title', 'insurance', 'registration', 'maintenance', 'other'];

        foreach ($validTypes as $type) {
            $document = Document::create([
                'user_id' => $this->user->id,
                'title' => "Document {$type}",
                'document_type' => $type,
                'upload_path' => "documents/{$type}.pdf",
                'upload_date' => now()->toDateString(),
            ]);
            $this->assertEquals($type, $document->document_type);
        }
    }

    // Vehicle Tests
    /** @test */
    public function user_can_create_vehicle()
    {
        $data = [
            'user_id' => $this->user->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
            'license_plate' => 'ABC123',
            'vehicle_type' => 'car',
            'status' => 'active',
            'notes' => 'Daily driver',
        ];

        $vehicle = Vehicle::create($data);

        $this->assertDatabaseHas('vehicles', [
            'user_id' => $this->user->id,
            'make' => 'Toyota',
            'license_plate' => 'ABC123',
        ]);
    }

    /** @test */
    public function vehicle_has_maintenance_records()
    {
        $vehicle = Vehicle::create([
            'user_id' => $this->user->id,
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2021,
            'license_plate' => 'XYZ789',
            'vehicle_type' => 'car',
        ]);

        MaintenanceRecord::create([
            'user_id' => $this->user->id,
            'vehicle_id' => $vehicle->id,
            'service_type' => 'Oil Change',
            'date' => now()->toDateString(),
            'cost' => 50.00,
        ]);

        $this->assertEquals(1, $vehicle->maintenanceRecords()->count());
    }

    /** @test */
    public function vehicle_validates_types()
    {
        $validTypes = ['car', 'motorcycle', 'truck', 'bicycle'];

        foreach ($validTypes as $type) {
            $vehicle = Vehicle::create([
                'user_id' => $this->user->id,
                'make' => 'Make',
                'model' => "Model {$type}",
                'year' => 2022,
                'license_plate' => "LP{$type}" . rand(100, 999),
                'vehicle_type' => $type,
            ]);
            $this->assertEquals($type, $vehicle->vehicle_type);
        }
    }

    // MaintenanceRecord Tests
    /** @test */
    public function user_can_create_maintenance_record()
    {
        $vehicle = Vehicle::create([
            'user_id' => $this->user->id,
            'make' => 'Ford',
            'model' => 'Focus',
            'year' => 2020,
            'license_plate' => 'MAINT01',
            'vehicle_type' => 'car',
        ]);

        $data = [
            'user_id' => $this->user->id,
            'vehicle_id' => $vehicle->id,
            'service_type' => 'Tire Rotation',
            'date' => now()->toDateString(),
            'cost' => 80.00,
            'description' => 'Regular maintenance',
            'mileage' => 50000,
        ];

        $record = MaintenanceRecord::create($data);

        $this->assertDatabaseHas('maintenance_records', [
            'user_id' => $this->user->id,
            'vehicle_id' => $vehicle->id,
            'service_type' => 'Tire Rotation',
        ]);
    }

    /** @test */
    public function maintenance_record_belongs_to_vehicle()
    {
        $vehicle = Vehicle::create([
            'user_id' => $this->user->id,
            'make' => 'BMW',
            'model' => 'X5',
            'year' => 2023,
            'license_plate' => 'BMW12345',
            'vehicle_type' => 'car',
        ]);

        $record = MaintenanceRecord::create([
            'user_id' => $this->user->id,
            'vehicle_id' => $vehicle->id,
            'service_type' => 'Brake Check',
            'date' => now()->toDateString(),
        ]);

        $this->assertEquals($vehicle->id, $record->vehicle->id);
    }

    // Recipe Tests
    /** @test */
    public function user_can_create_recipe()
    {
        $data = [
            'user_id' => $this->user->id,
            'name' => 'Pasta Carbonara',
            'cuisine' => 'italian',
            'difficulty' => 'medium',
            'prep_time' => 15,
            'cook_time' => 20,
            'servings' => 4,
            'ingredients_list' => ['pasta', 'eggs', 'bacon'],
        ];

        $recipe = Recipe::create($data);

        $this->assertDatabaseHas('recipes', [
            'user_id' => $this->user->id,
            'name' => 'Pasta Carbonara',
            'cuisine' => 'italian',
        ]);
        $this->assertEquals(['pasta', 'eggs', 'bacon'], $recipe->ingredients_list);
    }

    /** @test */
    public function recipe_validates_cuisines()
    {
        $validCuisines = ['italian', 'asian', 'mexican', 'mediterranean', 'other'];

        foreach ($validCuisines as $cuisine) {
            $recipe = Recipe::create([
                'user_id' => $this->user->id,
                'name' => "Recipe {$cuisine}",
                'cuisine' => $cuisine,
            ]);
            $this->assertEquals($cuisine, $recipe->cuisine);
        }
    }

    /** @test */
    public function recipe_validates_difficulties()
    {
        $validDifficulties = ['easy', 'medium', 'hard'];

        foreach ($validDifficulties as $difficulty) {
            $recipe = Recipe::create([
                'user_id' => $this->user->id,
                'name' => "Recipe {$difficulty}",
                'cuisine' => 'italian',
                'difficulty' => $difficulty,
            ]);
            $this->assertEquals($difficulty, $recipe->difficulty);
        }
    }

    // Meal Tests
    /** @test */
    public function user_can_create_meal()
    {
        $recipe = Recipe::create([
            'user_id' => $this->user->id,
            'name' => 'Spaghetti',
            'cuisine' => 'italian',
        ]);

        $data = [
            'user_id' => $this->user->id,
            'recipe_id' => $recipe->id,
            'date_eaten' => now()->toDateString(),
            'rating' => 5,
            'notes' => 'Delicious!',
            'is_favorite' => true,
        ];

        $meal = Meal::create($data);

        $this->assertDatabaseHas('meals', [
            'user_id' => $this->user->id,
            'recipe_id' => $recipe->id,
            'rating' => 5,
        ]);
    }

    /** @test */
    public function meal_belongs_to_recipe()
    {
        $recipe = Recipe::create([
            'user_id' => $this->user->id,
            'name' => 'Risotto',
            'cuisine' => 'italian',
        ]);

        $meal = Meal::create([
            'user_id' => $this->user->id,
            'recipe_id' => $recipe->id,
            'date_eaten' => now()->toDateString(),
        ]);

        $this->assertEquals($recipe->id, $meal->recipe->id);
    }

    /** @test */
    public function meal_tracks_favorite_status()
    {
        $recipe = Recipe::create([
            'user_id' => $this->user->id,
            'name' => 'Pizza',
            'cuisine' => 'italian',
        ]);

        $meal = Meal::create([
            'user_id' => $this->user->id,
            'recipe_id' => $recipe->id,
            'date_eaten' => now()->toDateString(),
            'is_favorite' => true,
        ]);

        $this->assertTrue($meal->is_favorite);
        $this->assertEquals(1, $this->user->meals()->where('is_favorite', true)->count());
    }

    // Ingredient Tests
    /** @test */
    public function user_can_create_ingredient()
    {
        $data = [
            'user_id' => $this->user->id,
            'name' => 'Tomato',
            'unit' => 'piece',
            'category' => 'vegetable',
        ];

        $ingredient = Ingredient::create($data);

        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Tomato',
            'category' => 'vegetable',
        ]);
    }

    /** @test */
    public function ingredient_validates_units()
    {
        $validUnits = ['g', 'ml', 'tbsp', 'cup', 'piece'];

        foreach ($validUnits as $unit) {
            $ingredient = Ingredient::create([
                'user_id' => $this->user->id,
                'name' => "Ingredient {$unit}",
                'unit' => $unit,
                'category' => 'vegetable',
            ]);
            $this->assertEquals($unit, $ingredient->unit);
        }
    }

    /** @test */
    public function ingredient_validates_categories()
    {
        $validCategories = ['vegetable', 'meat', 'grain', 'dairy', 'spice', 'other'];

        foreach ($validCategories as $category) {
            $ingredient = Ingredient::create([
                'user_id' => $this->user->id,
                'name' => "Ingredient {$category}",
                'unit' => 'piece',
                'category' => $category,
            ]);
            $this->assertEquals($category, $ingredient->category);
        }
    }

    // Trip Tests
    /** @test */
    public function user_can_create_trip()
    {
        $data = [
            'user_id' => $this->user->id,
            'destination' => 'Paris',
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDays(7)->toDateString(),
            'trip_type' => 'vacation',
            'status' => 'planned',
            'budget' => 5000.00,
        ];

        $trip = Trip::create($data);

        $this->assertDatabaseHas('trips', [
            'user_id' => $this->user->id,
            'destination' => 'Paris',
            'trip_type' => 'vacation',
        ]);
    }

    /** @test */
    public function trip_validates_statuses()
    {
        $validStatuses = ['planned', 'in_progress', 'completed'];

        foreach ($validStatuses as $status) {
            $trip = Trip::create([
                'user_id' => $this->user->id,
                'destination' => "Destination {$status}",
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(5)->toDateString(),
                'trip_type' => 'vacation',
                'status' => $status,
            ]);
            $this->assertEquals($status, $trip->status);
        }
    }

    /** @test */
    public function trip_has_flights_and_hotels()
    {
        $trip = Trip::create([
            'user_id' => $this->user->id,
            'destination' => 'London',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'trip_type' => 'business',
        ]);

        Flight::create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
            'airline' => 'British Airways',
            'flight_number' => 'BA112',
            'departure_date' => now()->toDateString(),
            'departure_time' => '10:00:00',
            'arrival_date' => now()->toDateString(),
            'arrival_time' => '14:00:00',
            'departure_airport' => 'JFK',
            'arrival_airport' => 'LHR',
        ]);

        Hotel::create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
            'name' => 'The Savoy',
            'city' => 'London',
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(9)->toDateString(),
            'nights' => 9,
        ]);

        $this->assertEquals(1, $trip->flights()->count());
        $this->assertEquals(1, $trip->hotels()->count());
    }

    // Flight Tests
    /** @test */
    public function user_can_create_flight()
    {
        $trip = Trip::create([
            'user_id' => $this->user->id,
            'destination' => 'Tokyo',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'trip_type' => 'vacation',
        ]);

        $data = [
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
            'airline' => 'JAL',
            'flight_number' => 'JL001',
            'departure_date' => now()->toDateString(),
            'departure_time' => '16:30:00',
            'arrival_date' => now()->addDays(1)->toDateString(),
            'arrival_time' => '08:45:00',
            'departure_airport' => 'LAX',
            'arrival_airport' => 'NRT',
            'seat' => '12A',
        ];

        $flight = Flight::create($data);

        $this->assertDatabaseHas('flights', [
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
            'airline' => 'JAL',
            'seat' => '12A',
        ]);
    }

    /** @test */
    public function flight_belongs_to_trip()
    {
        $trip = Trip::create([
            'user_id' => $this->user->id,
            'destination' => 'Sydney',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'trip_type' => 'vacation',
        ]);

        $flight = Flight::create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
            'airline' => 'Qantas',
            'flight_number' => 'QF001',
            'departure_date' => now()->toDateString(),
            'departure_time' => '09:00:00',
            'arrival_date' => now()->toDateString(),
            'arrival_time' => '15:00:00',
            'departure_airport' => 'LAX',
            'arrival_airport' => 'SYD',
        ]);

        $this->assertEquals($trip->id, $flight->trip->id);
    }

    // Hotel Tests
    /** @test */
    public function user_can_create_hotel()
    {
        $trip = Trip::create([
            'user_id' => $this->user->id,
            'destination' => 'Barcelona',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'trip_type' => 'vacation',
        ]);

        $data = [
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
            'name' => 'Hotel Arts',
            'city' => 'Barcelona',
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
            'nights' => 4,
            'cost_per_night' => 250.00,
            'total_cost' => 1000.00,
        ];

        $hotel = Hotel::create($data);

        $this->assertDatabaseHas('hotels', [
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
            'name' => 'Hotel Arts',
        ]);
    }

    /** @test */
    public function hotel_belongs_to_trip()
    {
        $trip = Trip::create([
            'user_id' => $this->user->id,
            'destination' => 'Rome',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'trip_type' => 'vacation',
        ]);

        $hotel = Hotel::create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
            'name' => 'Colonna Palace',
            'city' => 'Rome',
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
            'nights' => 4,
        ]);

        $this->assertEquals($trip->id, $hotel->trip->id);
    }
}

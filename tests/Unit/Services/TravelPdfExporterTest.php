<?php

namespace Tests\Unit\Services;

use App\Models\Activity;
use App\Models\Destination;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\TripBudget;
use App\Models\TripExpense;
use App\Models\TripParticipant;
use App\Models\User;
use App\Services\TravelPdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelPdfExporterTest extends TestCase
{
    use RefreshDatabase;

    private TravelPdfExporter $exporter;
    private User $user;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exporter = new TravelPdfExporter();
        $this->user = User::factory()->create();
        $this->trip = Trip::factory()->for($this->user)->create([
            'title' => 'Europe Adventure',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(20),
        ]);
    }

    /**
     * Test that export method returns a PDF response.
     *
     * @test
     */
    public function export_returns_pdf_response()
    {
        $response = $this->exporter->export($this->trip);

        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Test that exported PDF has correct filename.
     *
     * @test
     */
    public function export_generates_correct_filename()
    {
        $response = $this->exporter->export($this->trip);
        $disposition = $response->headers->get('Content-Disposition');

        $this->assertStringContainsString('itinerary-' . $this->trip->id, $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    /**
     * Test that HTML content includes trip title.
     *
     * @test
     */
    public function html_content_includes_trip_title()
    {
        // Use reflection to call private getHtmlContent method
        $reflection = new \ReflectionClass($this->exporter);
        $method = $reflection->getMethod('getHtmlContent');
        $method->setAccessible(true);

        $html = $method->invoke($this->exporter, $this->trip);

        $this->assertStringContainsString($this->trip->title, $html);
    }

    /**
     * Test that HTML content includes trip dates.
     *
     * @test
     */
    public function html_content_includes_trip_dates()
    {
        $reflection = new \ReflectionClass($this->exporter);
        $method = $reflection->getMethod('getHtmlContent');
        $method->setAccessible(true);

        $html = $method->invoke($this->exporter, $this->trip);

        $this->assertStringContainsString($this->trip->start_date->format('M d, Y'), $html);
        $this->assertStringContainsString($this->trip->end_date->format('M d, Y'), $html);
    }

    /**
     * Test that HTML content includes all itineraries.
     *
     * @test
     */
    public function html_content_includes_itineraries_and_activities()
    {
        // Create itineraries and activities
        $itinerary = Itinerary::factory()
            ->for($this->trip)
            ->create(['date' => $this->trip->start_date]);

        $activity = Activity::factory()
            ->for($itinerary)
            ->create(['title' => 'Visit Eiffel Tower']);

        $reflection = new \ReflectionClass($this->exporter);
        $method = $reflection->getMethod('getHtmlContent');
        $method->setAccessible(true);

        $html = $method->invoke($this->exporter, $this->trip->fresh());

        $this->assertStringContainsString('Visit Eiffel Tower', $html);
    }

    /**
     * Test that HTML content includes budget information.
     *
     * @test
     */
    public function html_content_includes_budget_summary()
    {
        TripBudget::factory()
            ->for($this->trip)
            ->for($this->user)
            ->create(['initial_amount' => 5000]);

        $reflection = new \ReflectionClass($this->exporter);
        $method = $reflection->getMethod('getHtmlContent');
        $method->setAccessible(true);

        $html = $method->invoke($this->exporter, $this->trip->fresh());

        $this->assertStringContainsString('Budget', $html);
    }

    /**
     * Test that HTML content includes destinations.
     *
     * @test
     */
    public function html_content_includes_destinations()
    {
        Destination::factory()
            ->for($this->trip)
            ->create(['name' => 'Paris', 'country' => 'France']);

        $reflection = new \ReflectionClass($this->exporter);
        $method = $reflection->getMethod('getHtmlContent');
        $method->setAccessible(true);

        $html = $method->invoke($this->exporter, $this->trip->fresh());

        $this->assertStringContainsString('Paris', $html);
        $this->assertStringContainsString('France', $html);
    }

    /**
     * Test that HTML content includes participants.
     *
     * @test
     */
    public function html_content_includes_participants()
    {
        TripParticipant::factory()
            ->for($this->trip)
            ->for($this->user)
            ->create(['name' => 'John Doe', 'email' => 'john@example.com']);

        $reflection = new \ReflectionClass($this->exporter);
        $method = $reflection->getMethod('getHtmlContent');
        $method->setAccessible(true);

        $html = $method->invoke($this->exporter, $this->trip->fresh());

        $this->assertStringContainsString('John Doe', $html);
    }
}

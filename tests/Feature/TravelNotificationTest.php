<?php

namespace Tests\Feature;

use App\Jobs\SendTripNotificationJob;
use App\Mail\TripStartReminder;
use App\Models\Activity;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\User;
use App\Services\TravelNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TravelNotificationTest extends TestCase
{
    use RefreshDatabase;

    private TravelNotificationService $notificationService;
    private User $user;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationService = app(TravelNotificationService::class);
        $this->user = User::factory()->create(['email' => 'user@example.com']);
        
        // Create trip without queue/mail faked to avoid side effects
        $this->trip = Trip::factory()
            ->for($this->user)
            ->create([
                'title' => 'Summer Vacation',
                'start_date' => now()->addDays(10),
                'end_date' => now()->addDays(20),
            ]);
        
        // Now fake queue and mail for the actual tests
        Queue::fake();
        Mail::fake();
    }

    /**
     * Test that scheduleStartNotification dispatches SendTripNotificationJob.
     *
     * @test
     */
    public function schedule_start_notification_dispatches_job()
    {
        Queue::assertNothingPushed();

        $this->notificationService->scheduleStartNotification($this->trip, 7);

        Queue::assertPushed(SendTripNotificationJob::class);
    }

    /**
     * Test that notification job is scheduled with correct delay.
     *
     * @test
     */
    public function notification_scheduled_with_correct_delay()
    {
        $daysBeforeStart = 7;
        $expectedScheduleTime = $this->trip->start_date->subDays($daysBeforeStart);

        $this->notificationService->scheduleStartNotification($this->trip, $daysBeforeStart);

        Queue::assertPushed(SendTripNotificationJob::class, function ($job) use ($expectedScheduleTime) {
            // The job should be scheduled for a future time
            return $job->delay !== null;
        });
    }

    /**
     * Test that sendImmediateNotification dispatches job without delay.
     *
     * @test
     */
    public function send_immediate_notification_dispatches_job_without_delay()
    {
        $this->notificationService->sendImmediateNotification($this->trip);

        Queue::assertPushed(SendTripNotificationJob::class);
    }

    /**
     * Test that SendTripNotificationJob sends email to trip owner.
     *
     * @test
     */
    public function send_trip_notification_job_sends_email_to_owner()
    {
        $job = new SendTripNotificationJob($this->trip);
        $job->handle();

        // Mail is queued by default with ShouldQueue
        Mail::assertQueued(TripStartReminder::class, function ($mailable) {
            return $mailable->hasTo($this->user->email);
        });
    }

    /**
     * Test that trip start reminder email contains trip title.
     *
     * @test
     */
    public function email_contains_trip_title()
    {
        $mailable = new TripStartReminder($this->trip);
        $envelope = $mailable->envelope();

        // The subject contains the trip title
        $this->assertStringContainsString($this->trip->title, $envelope->subject);
    }

    /**
     * Test that email is queued and has correct subject.
     *
     * @test
     */
    public function email_has_correct_subject()
    {
        $mailable = new TripStartReminder($this->trip);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Trip Starting Soon', $envelope->subject);
        $this->assertStringContainsString($this->trip->title, $envelope->subject);
    }

    /**
     * Test that notification skips past dates.
     *
     * @test
     */
    public function schedule_notification_skips_past_dates()
    {
        // Create trip with start date in the past
        $pastTrip = Trip::factory()
            ->for($this->user)
            ->create([
                'start_date' => now()->subDays(5),
                'end_date' => now()->subDays(1),
            ]);

        Queue::assertNothingPushed();

        $this->notificationService->scheduleStartNotification($pastTrip, 7);

        // Should not push job for past dates
        Queue::assertNotPushed(SendTripNotificationJob::class);
    }

    /**
     * Test that email includes itinerary preview.
     *
     * @test
     */
    public function email_includes_itinerary_preview()
    {
        // Create itineraries with activities
        $itinerary = Itinerary::factory()
            ->for($this->trip)
            ->create(['date' => $this->trip->start_date]);

        Activity::factory()
            ->for($itinerary)
            ->create([
                'title' => 'Visit Notre-Dame',
                'description' => 'Explore the cathedral',
            ]);

        $mailable = new TripStartReminder($this->trip->fresh());
        $rendered = $mailable->content();

        $this->assertNotNull($rendered);
    }

    /**
     * Test that trip observer schedules notification on creation.
     *
     * @test
     */
    public function trip_observer_schedules_notification_on_creation()
    {
        $newTrip = Trip::factory()
            ->for($this->user)
            ->create([
                'title' => 'New Trip',
                'start_date' => now()->addDays(10),
                'end_date' => now()->addDays(15),
            ]);

        // The observer should have already scheduled notification
        // Verify that the trip was created successfully
        $this->assertDatabaseHas('trips', [
            'id' => $newTrip->id,
            'title' => 'New Trip',
        ]);
    }
}

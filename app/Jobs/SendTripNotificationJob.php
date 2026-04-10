<?php

namespace App\Jobs;

use App\Mail\TripStartReminder;
use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTripNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param Trip $trip The trip to send notification for
     */
    public function __construct(
        public Trip $trip,
    ) {}

    /**
     * Execute the job.
     *
     * Sends the trip start reminder email to the trip owner.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            // Reload trip to ensure fresh data
            $trip = Trip::findOrFail($this->trip->id);

            // Send notification email to trip owner
            Mail::to($trip->user->email)
                ->send(new TripStartReminder($trip));

            Log::info('Trip notification sent', [
                'trip_id' => $trip->id,
                'user_id' => $trip->user_id,
                'email' => $trip->user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send trip notification', [
                'trip_id' => $this->trip->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - let the job be marked as failed
        }
    }
}

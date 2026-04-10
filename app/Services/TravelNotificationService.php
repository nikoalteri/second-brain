<?php

namespace App\Services;

use App\Jobs\SendTripNotificationJob;
use App\Models\Trip;
use Illuminate\Support\Facades\Log;

class TravelNotificationService
{
    /**
     * Schedule a trip start notification to be sent N days before trip starts.
     *
     * Calculates the delay from now until N days before the trip start date,
     * then dispatches the notification job to the queue.
     *
     * @param Trip $trip The trip to schedule notification for
     * @param int $daysBeforeStart Number of days before trip start (default: 7)
     * @return void
     */
    public function scheduleStartNotification(Trip $trip, int $daysBeforeStart = 7): void
    {
        try {
            // Calculate notification send time: N days before trip start
            $notificationDate = $trip->start_date->subDays($daysBeforeStart);

            // Only schedule if notification date is in the future
            if ($notificationDate->isFuture()) {
                SendTripNotificationJob::dispatch($trip)
                    ->delay($notificationDate);

                Log::info('Trip notification scheduled', [
                    'trip_id' => $trip->id,
                    'user_id' => $trip->user_id,
                    'scheduled_for' => $notificationDate->toDateTimeString(),
                ]);
            } else {
                Log::info('Trip notification skipped (date in past)', [
                    'trip_id' => $trip->id,
                    'scheduled_for' => $notificationDate->toDateTimeString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to schedule trip notification', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send trip notification immediately without delay.
     *
     * Useful for manual testing or urgent notifications that should go out right away.
     *
     * @param Trip $trip The trip to notify about
     * @return void
     */
    public function sendImmediateNotification(Trip $trip): void
    {
        try {
            SendTripNotificationJob::dispatch($trip);

            Log::info('Immediate trip notification dispatched', [
                'trip_id' => $trip->id,
                'user_id' => $trip->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch immediate notification', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

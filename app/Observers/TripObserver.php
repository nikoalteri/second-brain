<?php

namespace App\Observers;

use App\Models\Trip;
use App\Models\TripBudget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TripObserver
{
    /**
     * Handle trip creation event.
     * Auto-creates a TripBudget for the trip.
     *
     * @param Trip $trip
     * @return void
     */
    public function created(Trip $trip): void
    {
        try {
            DB::transaction(function () use ($trip) {
                // Auto-create TripBudget with default currency USD
                $initialAmount = $trip->budget ?? 0;

                TripBudget::create([
                    'user_id' => $trip->user_id,
                    'trip_id' => $trip->id,
                    'initial_amount' => $initialAmount,
                    'currency' => 'USD',
                ]);

                Log::info('Trip created, budget initialized', [
                    'trip_id' => $trip->id,
                    'user_id' => $trip->user_id,
                    'initial_budget' => $initialAmount,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error in TripObserver::created', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle trip update event.
     * Log status changes.
     *
     * @param Trip $trip
     * @return void
     */
    public function updated(Trip $trip): void
    {
        try {
            $originalStatus = $trip->getOriginal('status');

            if ($trip->status->value !== $originalStatus) {
                Log::info('Trip status changed', [
                    'trip_id' => $trip->id,
                    'user_id' => $trip->user_id,
                    'from_status' => $originalStatus,
                    'to_status' => $trip->status->value,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in TripObserver::updated', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle trip deletion event.
     * Log soft-delete.
     *
     * @param Trip $trip
     * @return void
     */
    public function deleted(Trip $trip): void
    {
        try {
            Log::info('Trip deleted', [
                'trip_id' => $trip->id,
                'user_id' => $trip->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TripObserver::deleted', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

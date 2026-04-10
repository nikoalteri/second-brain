<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TravelService
{
    /**
     * Create a new trip for the user.
     *
     * @param User $user The user creating the trip
     * @param array $data Trip data (title, start_date, end_date, description, notes)
     * @return Trip
     * @throws InvalidArgumentException
     */
    public function createTrip(User $user, array $data): Trip
    {
        $this->validateTripDates(
            $data['start_date'] ?? null,
            $data['end_date'] ?? null
        );

        return DB::transaction(function () use ($user, $data) {
            $trip = Trip::create([
                'user_id' => $user->id,
                'title' => $data['title'] ?? 'Untitled Trip',
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'notes' => $data['notes'] ?? null,
                'status' => TripStatus::PLANNING,
            ]);

            Log::info('Trip created', [
                'trip_id' => $trip->id,
                'user_id' => $user->id,
                'title' => $trip->title,
                'status' => $trip->status->value,
            ]);

            return $trip;
        });
    }

    /**
     * Update an existing trip.
     *
     * @param Trip $trip The trip to update
     * @param array $data Fields to update
     * @return Trip
     * @throws InvalidArgumentException
     */
    public function updateTrip(Trip $trip, array $data): Trip
    {
        // Validate dates if provided
        if (isset($data['start_date']) || isset($data['end_date'])) {
            $this->validateTripDates(
                $data['start_date'] ?? $trip->start_date,
                $data['end_date'] ?? $trip->end_date
            );
        }

        return DB::transaction(function () use ($trip, $data) {
            $trip->update($data);

            Log::info('Trip updated', [
                'trip_id' => $trip->id,
                'user_id' => $trip->user_id,
            ]);

            return $trip;
        });
    }

    /**
     * Soft delete a trip.
     *
     * @param Trip $trip
     * @return bool
     */
    public function deleteTrip(Trip $trip): bool
    {
        $result = $trip->delete();

        Log::info('Trip deleted', [
            'trip_id' => $trip->id,
            'user_id' => $trip->user_id,
        ]);

        return $result;
    }

    /**
     * Validate trip dates (start_date < end_date).
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateTripDates($startDate, $endDate): void
    {
        if (!$startDate || !$endDate) {
            throw new InvalidArgumentException('Both start_date and end_date are required.');
        }

        $start = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $end = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        if ($start->gte($end)) {
            throw new InvalidArgumentException('start_date must be before end_date.');
        }
    }
}

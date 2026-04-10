<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ItineraryService
{
    private ?TravelBudgetCalculator $budgetCalculator;

    public function __construct(?TravelBudgetCalculator $budgetCalculator = null)
    {
        $this->budgetCalculator = $budgetCalculator ?? app(TravelBudgetCalculator::class);
    }

    /**
     * Create a new itinerary for a trip.
     *
     * @param User $user
     * @param Trip $trip
     * @param array $data (date, destination_id, description)
     * @return Itinerary
     * @throws InvalidArgumentException
     */
    public function createItinerary(User $user, Trip $trip, array $data): Itinerary
    {
        // Validate date is within trip date range
        if (isset($data['date'])) {
            $itineraryDate = $data['date'] instanceof Carbon ? $data['date'] : Carbon::parse($data['date']);
            if ($itineraryDate->lt($trip->start_date) || $itineraryDate->gt($trip->end_date)) {
                throw new InvalidArgumentException('Itinerary date must be within trip date range.');
            }
        }

        return DB::transaction(function () use ($user, $trip, $data) {
            $itinerary = Itinerary::create([
                'user_id' => $user->id,
                'trip_id' => $trip->id,
                'destination_id' => $data['destination_id'] ?? null,
                'date' => $data['date'] ?? now()->toDateString(),
                'description' => $data['description'] ?? null,
            ]);

            Log::info('Itinerary created', [
                'itinerary_id' => $itinerary->id,
                'trip_id' => $trip->id,
                'user_id' => $user->id,
            ]);

            return $itinerary;
        });
    }

    /**
     * Add an activity to an itinerary.
     *
     * @param Itinerary $itinerary
     * @param array $data (title, start_time, end_time, description, type, cost, currency)
     * @return Activity
     * @throws InvalidArgumentException
     */
    public function addActivity(Itinerary $itinerary, array $data): Activity
    {
        $this->validateActivityData($data);

        return DB::transaction(function () use ($itinerary, $data) {
            $activity = Activity::create([
                'user_id' => $itinerary->user_id,
                'itinerary_id' => $itinerary->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? null,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'cost' => $data['cost'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'notes' => $data['notes'] ?? null,
            ]);

            // Check for conflicts after saving
            $conflicts = $this->detectConflicts($itinerary);
            if (!empty($conflicts)) {
                Log::warning('Activity conflicts detected', [
                    'activity_id' => $activity->id,
                    'itinerary_id' => $itinerary->id,
                    'conflict_count' => count($conflicts),
                ]);
            }

            Log::info('Activity added to itinerary', [
                'activity_id' => $activity->id,
                'itinerary_id' => $itinerary->id,
                'title' => $activity->title,
            ]);

            return $activity;
        });
    }

    /**
     * Detect overlapping activities in an itinerary.
     *
     * @param Itinerary $itinerary
     * @return array Array of conflicts: [['activity1_id' => X, 'activity2_id' => Y, ...], ...]
     */
    public function detectConflicts(Itinerary $itinerary): array
    {
        $activities = $itinerary->activities()->get();
        $conflicts = [];

        for ($i = 0; $i < count($activities); $i++) {
            for ($j = $i + 1; $j < count($activities); $j++) {
                $activity1 = $activities[$i];
                $activity2 = $activities[$j];

                if ($this->timeRangesOverlap(
                    $activity1->start_time,
                    $activity1->end_time,
                    $activity2->start_time,
                    $activity2->end_time
                )) {
                    $conflicts[] = [
                        'activity1_id' => $activity1->id,
                        'activity2_id' => $activity2->id,
                        'activity1_title' => $activity1->title,
                        'activity2_title' => $activity2->title,
                        'overlap_start' => max($activity1->start_time, $activity2->start_time),
                        'overlap_end' => min($activity1->end_time, $activity2->end_time),
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Update an activity in an itinerary.
     *
     * @param Activity $activity
     * @param array $data
     * @return Activity
     * @throws InvalidArgumentException
     */
    public function updateActivity(Activity $activity, array $data): Activity
    {
        if (isset($data['start_time']) || isset($data['end_time'])) {
            $this->validateActivityData([
                'start_time' => $data['start_time'] ?? $activity->start_time,
                'end_time' => $data['end_time'] ?? $activity->end_time,
                'title' => $data['title'] ?? $activity->title,
            ]);
        }

        return DB::transaction(function () use ($activity, $data) {
            $activity->update($data);

            // Re-check conflicts after update
            $conflicts = $this->detectConflicts($activity->itinerary);
            if (!empty($conflicts)) {
                Log::warning('Activity conflicts detected after update', [
                    'activity_id' => $activity->id,
                    'itinerary_id' => $activity->itinerary_id,
                    'conflict_count' => count($conflicts),
                ]);
            }

            Log::info('Activity updated', [
                'activity_id' => $activity->id,
                'itinerary_id' => $activity->itinerary_id,
            ]);

            return $activity;
        });
    }

    /**
     * Check if two time ranges overlap.
     *
     * @param DateTime|Carbon|string $start1
     * @param DateTime|Carbon|string $end1
     * @param DateTime|Carbon|string $start2
     * @param DateTime|Carbon|string $end2
     * @return bool
     */
    private function timeRangesOverlap($start1, $end1, $start2, $end2): bool
    {
        $s1 = $start1 instanceof Carbon ? $start1 : Carbon::parse($start1);
        $e1 = $end1 instanceof Carbon ? $end1 : Carbon::parse($end1);
        $s2 = $start2 instanceof Carbon ? $start2 : Carbon::parse($start2);
        $e2 = $end2 instanceof Carbon ? $end2 : Carbon::parse($end2);

        // Ranges overlap if: start1 < end2 AND start2 < end1
        return $s1->lt($e2) && $s2->lt($e1);
    }

    /**
     * Validate activity data.
     *
     * @param array $data
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateActivityData(array $data): void
    {
        if (!isset($data['title']) || empty($data['title'])) {
            throw new InvalidArgumentException('Activity title is required.');
        }

        if (!isset($data['start_time']) || !isset($data['end_time'])) {
            throw new InvalidArgumentException('Both start_time and end_time are required.');
        }

        $start = $data['start_time'] instanceof Carbon ? $data['start_time'] : Carbon::parse($data['start_time']);
        $end = $data['end_time'] instanceof Carbon ? $data['end_time'] : Carbon::parse($data['end_time']);

        if ($start->gte($end)) {
            throw new InvalidArgumentException('start_time must be before end_time.');
        }
    }
}

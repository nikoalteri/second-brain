<?php

namespace App\Observers;

use App\Models\Itinerary;
use App\Services\ItineraryService;
use Illuminate\Support\Facades\Log;

class ItineraryObserver
{
    private ItineraryService $itineraryService;

    public function __construct(ItineraryService $itineraryService)
    {
        $this->itineraryService = $itineraryService;
    }

    /**
     * Handle itinerary creation event.
     * Initialize any dependent data if needed.
     *
     * @param Itinerary $itinerary
     * @return void
     */
    public function created(Itinerary $itinerary): void
    {
        try {
            Log::info('Itinerary created', [
                'itinerary_id' => $itinerary->id,
                'trip_id' => $itinerary->trip_id,
                'user_id' => $itinerary->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in ItineraryObserver::created', [
                'itinerary_id' => $itinerary->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle itinerary update event.
     * Detect and log activity conflicts.
     *
     * @param Itinerary $itinerary
     * @return void
     */
    public function updated(Itinerary $itinerary): void
    {
        try {
            // Detect conflicts after update
            $conflicts = $this->itineraryService->detectConflicts($itinerary);

            if (!empty($conflicts)) {
                $conflictDetails = [];
                foreach ($conflicts as $conflict) {
                    $conflictDetails[] = sprintf(
                        'Activity %d (%s) overlaps with Activity %d (%s) from %s to %s',
                        $conflict['activity1_id'],
                        $conflict['activity1_title'],
                        $conflict['activity2_id'],
                        $conflict['activity2_title'],
                        $conflict['overlap_start']->format('Y-m-d H:i'),
                        $conflict['overlap_end']->format('Y-m-d H:i')
                    );
                }

                Log::warning('Itinerary has activity conflicts', [
                    'itinerary_id' => $itinerary->id,
                    'trip_id' => $itinerary->trip_id,
                    'conflict_count' => count($conflicts),
                    'conflicts' => $conflictDetails,
                ]);
            }

            Log::info('Itinerary updated', [
                'itinerary_id' => $itinerary->id,
                'trip_id' => $itinerary->trip_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in ItineraryObserver::updated', [
                'itinerary_id' => $itinerary->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle itinerary deletion event.
     *
     * @param Itinerary $itinerary
     * @return void
     */
    public function deleted(Itinerary $itinerary): void
    {
        try {
            Log::info('Itinerary deleted', [
                'itinerary_id' => $itinerary->id,
                'trip_id' => $itinerary->trip_id,
                'user_id' => $itinerary->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in ItineraryObserver::deleted', [
                'itinerary_id' => $itinerary->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

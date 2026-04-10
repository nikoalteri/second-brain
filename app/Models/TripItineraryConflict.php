<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class TripItineraryConflict extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $table = 'trip_itinerary_conflicts';

    protected $fillable = [
        'user_id',
        'trip_id',
        'itinerary_id',
        'activity_1_id',
        'activity_2_id',
        'conflict_start',
        'conflict_end',
        'resolved_at',
        'notes',
    ];

    protected $casts = [
        'conflict_start' => 'datetime',
        'conflict_end' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the user who owns this conflict record.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the trip this conflict belongs to.
     *
     * @return BelongsTo
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Get the itinerary where the conflict occurred.
     *
     * @return BelongsTo
     */
    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(Itinerary::class);
    }

    /**
     * Get the first conflicting activity.
     *
     * @return BelongsTo
     */
    public function activity1(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_1_id');
    }

    /**
     * Get the second conflicting activity.
     *
     * @return BelongsTo
     */
    public function activity2(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_2_id');
    }

    /**
     * Check if this conflict has been resolved.
     *
     * @return bool
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Mark this conflict as resolved.
     *
     * @return void
     */
    public function markAsResolved(): void
    {
        $this->update(['resolved_at' => now()]);
    }
}

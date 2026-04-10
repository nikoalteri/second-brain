<?php

namespace App\Models;

use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\HasUserScoping;

class Trip extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'start_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => TripStatus::class,
    ];

    protected $appends = ['destination_count', 'activity_count'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(Destination::class);
    }

    public function itineraries(): HasMany
    {
        return $this->hasMany(Itinerary::class);
    }

    public function budget(): HasOne
    {
        return $this->hasOne(TripBudget::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TripParticipant::class);
    }

    public function getDestinationCountAttribute(): int
    {
        return $this->destinations()->count();
    }

    public function getActivityCountAttribute(): int
    {
        return $this->itineraries()
            ->withCount('activities')
            ->get()
            ->sum('activities_count');
    }
}

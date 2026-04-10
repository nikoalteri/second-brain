<?php

namespace App\Models;

use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceTask extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'property_id',
        'name',
        'type',
        'frequency',
        'description',
        'last_completed_date',
        'next_due_date',
        'status',
    ];

    protected $casts = [
        'last_completed_date' => 'date',
        'next_due_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function propertyMaintenanceRecords(): HasMany
    {
        return $this->hasMany(PropertyMaintenanceRecord::class);
    }

    public function daysUntilDue(): ?int
    {
        if (!$this->next_due_date) {
            return null;
        }

        return now()->diffInDays($this->next_due_date, false);
    }
}

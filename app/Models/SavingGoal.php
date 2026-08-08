<?php

namespace App\Models;

use App\Enums\SavingGoalStatus;
use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingGoal extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $attributes = [
        'current_amount' => 0,
        'status' => 'active',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'target_amount',
        'current_amount',
        'target_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'target_date' => 'date',
        'status' => SavingGoalStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(SavingGoalContribution::class);
    }

    public function getProgressPercentAttribute(): float
    {
        $target = (float) $this->target_amount;

        if ($target <= 0) {
            return 0.0;
        }

        return round(min(100.0, max(0.0, ((float) $this->current_amount / $target) * 100)), 1);
    }
}

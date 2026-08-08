<?php

namespace App\Models;

use App\Enums\SavingGoalStatus;
use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingGoal extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $attributes = [
        'status' => 'active',
    ];

    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'target_amount',
        'target_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'target_date' => 'date',
        'status' => SavingGoalStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Progress is the linked account's live balance — never a separately-maintained running
     * total — so it can never drift from what the account actually holds.
     */
    public function getCurrentAmountAttribute(): float
    {
        return (float) ($this->account?->balance ?? 0);
    }

    public function getProgressPercentAttribute(): float
    {
        $target = (float) $this->target_amount;

        if ($target <= 0) {
            return 0.0;
        }

        return round(min(100.0, max(0.0, ($this->current_amount / $target) * 100)), 1);
    }

    public function getIsAchievedAttribute(): bool
    {
        return (float) $this->target_amount > 0 && $this->current_amount >= (float) $this->target_amount;
    }
}

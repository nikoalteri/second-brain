<?php

namespace App\Models;

use App\Enums\NotificationSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Durable state of one notifiable condition. Always query with an explicit user_id:
 * the scheduled digest has no authenticated user.
 */
class NotificationCondition extends Model
{
    protected $fillable = [
        'user_id',
        'trigger',
        'subject_type',
        'subject_id',
        'period_key',
        'active',
        'current_severity',
        'max_notified_severity',
        'episode',
        'activated_at',
        'resolved_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'current_severity' => NotificationSeverity::class,
        'max_notified_severity' => NotificationSeverity::class,
        'episode' => 'integer',
        'activated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->withoutGlobalScope('user');
    }
}

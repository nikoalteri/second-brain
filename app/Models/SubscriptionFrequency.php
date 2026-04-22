<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionFrequency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'months_interval',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'months_interval' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}

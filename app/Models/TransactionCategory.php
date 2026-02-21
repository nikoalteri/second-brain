<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasUserScoping;

class TransactionCategory extends Model
{
    use HasUserScoping;

    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'color',
        'icon',
        'budget_monthly',
        'is_active'
    ];

    protected $casts = [
        'budget_monthly' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TransactionCategory::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getIsParentAttribute(): bool
    {
        return $this->children()->exists();
    }
}

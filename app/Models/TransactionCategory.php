<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionCategory extends Model
{
    use Auditable, HasUserScoping;

    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'scope',
        'is_active',
    ];

    protected $casts = [
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

    public function budgets(): HasMany
    {
        return $this->hasMany(CategoryBudget::class);
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

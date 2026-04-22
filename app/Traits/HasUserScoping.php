<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasUserScoping
{
    protected static function bootHasUserScoping(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (auth()->check() && ! auth()->user()?->hasRole('superadmin')) {
                $query->where('user_id', auth()->id());
            }
        });

        static::creating(function ($model) {
            if (auth()->check() && empty($model->user_id)) {
                $model->user_id = auth()->id();
            }
        });
    }

    public function scopeWithoutUserScope(Builder $query)
    {
        return $query->withoutGlobalScope('user');
    }
}

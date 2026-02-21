<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasUserScoping
{
    protected static function bootHasUserScoping(): void
    {
        static::addGlobalScope('user', function (Builder $query) {
            if (auth()->check()) {
                $query->where('user_id', auth()->id());
            }
        });

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }

    public function scopeWithoutUserScope(Builder $query)
    {
        $query->withoutGlobalScope('user');
    }
}

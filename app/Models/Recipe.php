<?php

namespace App\Models;

use App\Enums\RecipeCuisine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class Recipe extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'cuisine',
        'ingredients_list',
        'instructions',
        'prep_time_minutes',
        'cook_time_minutes',
        'servings',
    ];

    protected $casts = [
        'ingredients_list' => 'array',
        'prep_time_minutes' => 'integer',
        'cook_time_minutes' => 'integer',
        'servings' => 'integer',
        'cuisine' => RecipeCuisine::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

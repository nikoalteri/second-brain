<?php

namespace App\Models;

use App\Enums\MealType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class Meal extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'recipe_id',
        'date_eaten',
        'meal_type',
        'calories',
        'notes',
        'is_favorite',
    ];

    protected $casts = [
        'date_eaten' => 'date',
        'calories' => 'integer',
        'is_favorite' => 'boolean',
        'meal_type' => MealType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}

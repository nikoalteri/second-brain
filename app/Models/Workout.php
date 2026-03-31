<?php

namespace App\Models;

use App\Enums\WorkoutType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class Workout extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'date',
        'type',
        'duration_minutes',
        'calories_burned',
        'distance',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'type' => WorkoutType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

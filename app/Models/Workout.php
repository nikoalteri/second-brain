<?php

namespace App\Models;

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
        'exercise_name',
        'distance_km',
        'intensity_level',
        'location',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'type' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

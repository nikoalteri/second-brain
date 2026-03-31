<?php

namespace App\Models;

use App\Enums\HabitFrequency;
use App\Enums\HabitDifficulty;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class Habit extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'frequency',
        'difficulty',
        'start_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'frequency' => HabitFrequency::class,
        'difficulty' => HabitDifficulty::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

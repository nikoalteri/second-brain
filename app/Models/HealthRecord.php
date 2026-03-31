<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class HealthRecord extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'date',
        'weight',
        'height',
        'heart_rate',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'temperature',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'weight' => 'decimal:2',
        'temperature' => 'decimal:1',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

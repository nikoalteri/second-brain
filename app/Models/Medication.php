<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class Medication extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'name',
        'dosage',
        'frequency',
        'start_date',
        'end_date',
        'reason',
        'doctor_name',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isActive()
    {
        $today = now()->toDateString();
        return $today >= $this->start_date->toDateString() &&
               ($this->end_date === null || $today <= $this->end_date->toDateString());
    }
}

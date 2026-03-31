<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class MedicalRecord extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'date',
        'type',
        'doctor_name',
        'clinic_hospital',
        'description',
        'notes',
        'file_path',
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

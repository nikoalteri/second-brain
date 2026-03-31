<?php

namespace App\Models;

use App\Enums\MedicationDosageUnit;
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
        'dosage_unit',
        'frequency',
        'start_date',
        'end_date',
        'reason',
        'side_effects',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'dosage' => 'decimal:2',
        'dosage_unit' => MedicationDosageUnit::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

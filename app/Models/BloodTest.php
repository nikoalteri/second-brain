<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class BloodTest extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'date',
        'hemoglobin',
        'hematocrit',
        'glucose',
        'cholesterol',
        'hdl',
        'ldl',
        'triglycerides',
        'white_blood_cells',
        'red_blood_cells',
        'platelets',
        'notes',
        'lab_name',
    ];

    protected $casts = [
        'date' => 'date',
        'hemoglobin' => 'decimal:1',
        'hematocrit' => 'decimal:1',
        'glucose' => 'decimal:2',
        'cholesterol' => 'decimal:2',
        'hdl' => 'decimal:2',
        'ldl' => 'decimal:2',
        'triglycerides' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

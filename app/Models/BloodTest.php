<?php

namespace App\Models;

use App\Enums\BloodTestResultStatus;
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
        'test_name',
        'hemoglobin',
        'hematocrit',
        'white_blood_cells',
        'red_blood_cells',
        'platelets',
        'result_status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'hemoglobin' => 'decimal:1',
        'hematocrit' => 'decimal:1',
        'white_blood_cells' => 'decimal:2',
        'red_blood_cells' => 'decimal:2',
        'platelets' => 'integer',
        'result_status' => BloodTestResultStatus::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

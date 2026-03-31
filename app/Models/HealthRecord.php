<?php

namespace App\Models;

use App\Enums\HealthRecordType;
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
        'type',
        'value',
        'unit',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'type' => HealthRecordType::class,
        'value' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

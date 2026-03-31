<?php

namespace App\Models;

use App\Enums\MaintenanceRecordType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class MaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'date',
        'type',
        'description',
        'cost',
        'odometer_reading',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'cost' => 'decimal:2',
        'odometer_reading' => 'integer',
        'type' => MaintenanceRecordType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}

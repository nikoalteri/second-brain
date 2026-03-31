<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserScoping;

class MaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $table = 'maintenance_records';

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'service_type',
        'date',
        'cost',
        'description',
        'mileage',
    ];

    protected $casts = [
        'date' => 'date',
        'cost' => 'decimal:2',
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

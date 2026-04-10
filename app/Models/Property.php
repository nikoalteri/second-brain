<?php

namespace App\Models;

use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'address',
        'property_type',
        'lease_start_date',
        'lease_end_date',
        'estimated_value',
    ];

    protected $casts = [
        'lease_start_date' => 'date',
        'lease_end_date' => 'date',
        'estimated_value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maintenanceTasks(): HasMany
    {
        return $this->hasMany(MaintenanceTask::class);
    }

    public function utilities(): HasMany
    {
        return $this->hasMany(Utility::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function propertyMaintenanceRecords(): HasMany
    {
        return $this->hasMany(PropertyMaintenanceRecord::class, 'property_id');
    }

    public function formattedAddress(): string
    {
        return $this->address;
    }

    public function formattedValue(): string
    {
        return '$' . number_format($this->estimated_value, 2);
    }
}

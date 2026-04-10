<?php

namespace App\Models;

use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Inventory extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'property_id',
        'inventory_category_id',
        'name',
        'description',
        'value',
        'location',
        'purchase_date',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'value' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function currentValue(): float
    {
        if (!$this->purchase_date || !$this->category) {
            return $this->value;
        }

        $yearsOwned = $this->purchase_date->diffInYears(Carbon::now());
        $depreciationRate = $this->category->depreciation_rate / 100;
        $depreciatedValue = $this->value * pow(1 - $depreciationRate, $yearsOwned);

        return max($depreciatedValue, 0);
    }

    public function yearsOwned(): ?int
    {
        if (!$this->purchase_date) {
            return null;
        }

        return $this->purchase_date->diffInYears(Carbon::now());
    }
}

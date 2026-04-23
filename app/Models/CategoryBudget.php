<?php

namespace App\Models;

use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryBudget extends Model
{
    use HasFactory, HasUserScoping;

    protected $fillable = [
        'user_id',
        'transaction_category_id',
        'period_start',
        'amount',
    ];

    protected $casts = [
        'period_start' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactionCategory(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }
}

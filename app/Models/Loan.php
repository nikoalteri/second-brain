<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\LoanStatus;
use App\Traits\HasUserScoping;

class Loan extends Model
{
    use HasFactory, HasUserScoping;

    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'total_amount',
        'monthly_payment',
        'interest_rate',
        'is_variable_rate',
        'withdrawal_day',
        'skip_weekends',
        'start_date',
        'end_date',
        'total_installments',
        'paid_installments',
        'remaining_amount',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'is_variable_rate' => 'boolean',
        'remaining_amount' => 'decimal:2',
        'skip_weekends' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => LoanStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    /**
     * Scope: filter to records belonging to the authenticated user.
     * Used by Lighthouse @scope(name: "belongsToAuthUser") on GraphQL paginated queries.
     */
    public function scopeBelongsToAuthUser($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('user_id', auth()->id());
    }
}

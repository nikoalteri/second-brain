<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'transaction_type_id',
        'to_account_id',
        'transaction_category_id',
        'amount',
        'date',
        'competence_month',
        'description',
        'notes',
        'is_transfer',
        'transfer_pair_id',
        'transfer_direction',
    ];

    protected $casts = [
        'date'        => 'date',
        'amount'      => 'decimal:2',
        'is_transfer' => 'boolean',
    ];

    // RELAZIONI
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function type()
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    public function category()
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

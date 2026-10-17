<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Deliberately has no HasUserScoping: a batch is always personal, even for a superadmin, so every
 * lookup states the owner explicitly instead of relying on an ambient scope.
 */
class ImportBatch extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'credit_card_id',
        'currency',
        'status',
        'file_format',
        'original_filename',
        'row_count',
        'statement_ending_balance',
        'period_start',
        'period_end',
        'mapping',
        'mapping_version',
    ];

    protected $casts = [
        'status' => ImportBatchStatus::class,
        'statement_ending_balance' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'mapping' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }
}

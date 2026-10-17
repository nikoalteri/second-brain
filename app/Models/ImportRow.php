<?php

namespace App\Models;

use App\Enums\ImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $fillable = [
        'import_batch_id', 'row_number', 'raw_cells', 'status', 'normalized', 'error', 'fingerprint', 'duplicate_class', 'flags',
    ];

    protected $casts = [
        'status' => ImportRowStatus::class,
        'raw_cells' => 'encrypted:array',
        'normalized' => 'encrypted:array',
        'flags' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}

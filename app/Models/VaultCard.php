<?php

namespace App\Models;

use App\Enums\CardBrand;
use App\Enums\VaultCardType;
use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VaultCard extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'type',
        'brand',
        'card_number',
        'expiry_month',
        'expiry_year',
        'cvv',
        'pin',
        'security_code',
    ];

    /**
     * Never leak via toArray()/JSON serialization by default — see CreditCard for the same
     * defense-in-depth rationale (these are only ever exposed through VaultCardSensitiveResource,
     * gated by the vault-unlock middleware).
     */
    protected $hidden = [
        'card_number',
        'cvv',
        'pin',
        'security_code',
    ];

    protected $casts = [
        'card_number' => 'encrypted',
        'cvv' => 'encrypted',
        'pin' => 'encrypted',
        'security_code' => 'encrypted',
        'type' => VaultCardType::class,
        'brand' => CardBrand::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

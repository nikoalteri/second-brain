<?php

namespace App\Models;

use App\Enums\CardBrand;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Enums\InterestCalculationMethod;
use App\Traits\Auditable;
use App\Traits\HasUserScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditCard extends Model
{
    use Auditable, HasFactory, SoftDeletes, HasUserScoping;

    protected $appends = [
        'available_credit',
        'is_unlimited',
    ];

    /** Maintained by the system on every posting; recording it would only add noise to the audit trail. */
    protected array $auditIgnored = ['current_balance'];

    /** Sensitive: an audit row says the column changed, never its value. */
    protected array $auditRedacted = ['card_number', 'expiry_month', 'expiry_year', 'cvv', 'pin', 'security_code'];

    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'type',
        'brand',
        'credit_limit',
        'fixed_payment',
        'interest_rate',
        'stamp_duty_amount',
        'fixed_payment_includes_stamp_duty',
        'statement_day',
        'due_day',
        'skip_weekends',
        'current_balance',
        'opening_balance',
        'status',
        'start_date',
        'interest_calculation_method',
        'card_number',
        'expiry_month',
        'expiry_year',
        'cvv',
        'pin',
        'security_code',
    ];

    /**
     * Never leak via toArray()/JSON serialization by default — the vault fields are only ever
     * exposed through CreditCardVaultResource, which the vault-unlock middleware gates. This is
     * defense in depth: API resources already whitelist fields explicitly, but this ensures a
     * stray `$creditCard->toArray()` elsewhere in the app can't accidentally surface them.
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
        'type' => CreditCardType::class,
        'brand' => CardBrand::class,
        'credit_limit' => 'decimal:2',
        'fixed_payment' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'stamp_duty_amount' => 'decimal:2',
        'fixed_payment_includes_stamp_duty' => 'boolean',
        'skip_weekends' => 'boolean',
        'current_balance' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'status' => CreditCardStatus::class,
        'start_date' => 'date',
        'interest_calculation_method' => InterestCalculationMethod::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (CreditCard $card): void {
            // Filament's form always submits opening_balance explicitly (it's a required
            // field defaulting to 0), so "key absent" alone doesn't catch the common case of
            // a user filling only "Current balance" and leaving "Opening balance" at its
            // untouched 0 default. Treat opening_balance as "not meaningfully provided" when
            // it is either absent or exactly 0 while current_balance carries a real value —
            // there is no legitimate scenario where a card is created with a non-zero
            // current_balance but a deliberately-zero opening_balance and no expenses to
            // account for the difference.
            $openingBalance = (float) ($card->opening_balance ?? 0);
            $currentBalance = $card->current_balance !== null ? (float) $card->current_balance : null;

            if ($openingBalance === 0.0 && $currentBalance !== null && $currentBalance !== 0.0) {
                // Backward compat: caller only set current_balance (old API/tests/factory
                // shape) — infer opening_balance from it, current_balance is already correct.
                $card->opening_balance = $currentBalance;
            } else {
                // A brand-new record has no expenses or payments yet — it cannot exist until
                // this row is inserted — so current_balance can only ever legitimately equal
                // opening_balance at creation time. Force it, regardless of whatever
                // (possibly stale or defaulted) value the caller separately submitted for
                // current_balance, so a card created via "Opening balance" alone doesn't sit
                // with a wrong current_balance/available_credit until the next unrelated sync.
                // Use the already-computed $openingBalance float, not the raw opening_balance
                // attribute — the latter is null (not 0) whenever the caller never set it at
                // all, which would otherwise insert a NULL into the NOT NULL current_balance
                // column for an ordinary current_balance: 0 / no-opening_balance card.
                $card->current_balance = $openingBalance;
            }
        });

        static::saving(function (CreditCard $creditCard): void {
            $type = $creditCard->type instanceof \BackedEnum
                ? $creditCard->type->value
                : (string) $creditCard->type;

            if ($type !== CreditCardType::CHARGE->value) {
                return;
            }

            $creditCard->fixed_payment = null;
            $creditCard->interest_rate = null;
            $creditCard->interest_calculation_method = InterestCalculationMethod::DAILY_BALANCE;
        });
    }

    /**
     * `type`/`brand`/`status`/`interest_calculation_method` are cast to backed enums for app-side
     * logic, but graphql-php's String scalar can't serialize an enum object directly (it only
     * accepts scalars/__toString) — these give the GraphQL schema (see graphql/schema.graphql,
     * @method directive) a plain string to resolve instead of failing the whole query when the
     * field is requested (e.g. the credit card edit form).
     */
    public function graphqlType(): string
    {
        return $this->type->value;
    }

    public function graphqlBrand(): ?string
    {
        return $this->brand?->value;
    }

    public function graphqlStatus(): string
    {
        return $this->status->value;
    }

    public function graphqlInterestCalculationMethod(): ?string
    {
        return $this->interest_calculation_method?->value;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(CreditCardCycle::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditCardPayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(CreditCardExpense::class);
    }

    public function getIsUnlimitedAttribute(): bool
    {
        return $this->credit_limit === null;
    }

    public function getAvailableCreditAttribute(): ?float
    {
        if ($this->credit_limit === null) {
            return null;
        }

        return round(max(0.0, (float) $this->credit_limit - (float) $this->current_balance), 2);
    }

    /**
     * Scope: filter to records belonging to the authenticated user.
     * Used by Lighthouse @scope(name: "belongsToAuthUser") on GraphQL paginated queries.
     */
    public function scopeBelongsToAuthUser($query): \Illuminate\Database\Eloquent\Builder
    {
        if (auth()->user()?->hasRole('superadmin')) {
            return $query;
        }

        return $query->where('user_id', auth()->id());
    }
}

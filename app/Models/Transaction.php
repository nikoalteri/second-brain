<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CreditCardPayment;
use App\Models\LoanPayment;
use App\Traits\HasUserScoping;
use Carbon\Carbon;

class Transaction extends Model
{
    use HasFactory, SoftDeletes, HasUserScoping;

    protected $fillable = [
        'user_id',
        'account_id',
        'transaction_type_id',
        'to_account_id',
        'loan_payment_id',
        'credit_card_payment_id',
        'subscription_id',
        'transaction_category_id',
        'amount',
        'date',
        'subscription_renewal_date',
        'competence_month',
        'description',
        'notes',
        'is_transfer',
        'transfer_pair_id',
        'transfer_direction',
    ];

    protected $casts = [
        'date'        => 'date',
        'subscription_renewal_date' => 'date',
        'amount'      => 'decimal:2',
        'is_transfer' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Transaction $transaction) {
            if ($transaction->transaction_type_id) {
                $type = TransactionType::query()->find($transaction->transaction_type_id);
                $isTransfer = $transaction->is_transfer
                    || strcasecmp((string) ($type?->name ?? ''), 'Transfer') === 0;

                if ($isTransfer) {
                    $transaction->amount = $transaction->transfer_direction === 'in'
                        ? abs((float) $transaction->amount)
                        : -abs((float) $transaction->amount);
                } elseif ($type !== null) {
                    $transaction->amount = $type->is_income
                        ? abs((float) $transaction->amount)
                        : -abs((float) $transaction->amount);
                }
            }

            if ($transaction->date) {
                $transaction->competence_month = Carbon::parse($transaction->date)->format('Y-m');
            }

            if (! $transaction->account_id) {
                return;
            }

            $accountOwnerId = Account::withoutGlobalScopes()
                ->whereKey($transaction->account_id)
                ->value('user_id');

            if ($accountOwnerId) {
                $transaction->user_id = $accountOwnerId;
            }
        });
    }

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

    public function loanPayment(): BelongsTo
    {
        return $this->belongsTo(LoanPayment::class);
    }

    public function creditCardPayment(): BelongsTo
    {
        return $this->belongsTo(CreditCardPayment::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function scopeDateFrom($query, string $date)
    {
        return $query->where('date', '>=', $date);
    }

    public function scopeDateTo($query, string $date)
    {
        return $query->where('date', '<=', $date);
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

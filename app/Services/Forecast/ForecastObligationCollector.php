<?php

namespace App\Services\Forecast;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\LoanPaymentStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-only discovery and classification of the commitments that fall in a forecast window.
 *
 * Every root is filtered on the requested owner explicitly and the global user scope is
 * lifted, so the result never depends on who is authenticated (a superadmin gets their own
 * personal view, like UpcomingPaymentsService). It never writes and never guesses: a
 * missing source, an unknown currency or an unprovable settlement is reported as a reason.
 */
class ForecastObligationCollector
{
    public const HORIZON_DAYS = 30;

    /** Upper bound on projected steps per subscription, so corrupt dates cannot spin forever. */
    private const MAX_PROJECTION_STEPS = 1000;

    /**
     * Paid rows normally own a posting; one that does not is a coverage warning. Only recent
     * rows are checked so legacy history does not raise the warning forever.
     */
    private const PAID_LOOKBACK_DAYS = 90;

    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    /**
     * Obligations due from $today through $today + 30 days inclusive, plus overdue items that
     * are not provably settled.
     *
     * @return list<ForecastObligation>
     */
    public function collect(User $user, CarbonInterface $today): array
    {
        $today = Carbon::instance($today)->startOfDay();
        $until = $today->copy()->addDays(self::HORIZON_DAYS);

        $accounts = Account::query()->withoutUserScope()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('id');

        $cards = CreditCard::query()->withoutUserScope()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('id');

        $obligations = [
            ...$this->loanObligations($user, $accounts, $today, $until),
            ...$this->cardPaymentObligations($cards, $accounts, $today, $until),
            ...$this->subscriptionObligations($user, $accounts, $cards, $today, $until),
        ];

        $unique = [];
        foreach ($obligations as $obligation) {
            $unique[$obligation->id()] = $obligation;
        }

        $sorted = array_values($unique);
        usort($sorted, fn (ForecastObligation $a, ForecastObligation $b) => [$a->dueDate, $a->id()] <=> [$b->dueDate, $b->id()]);

        return $sorted;
    }

    /**
     * @param Collection<int, Account> $accounts
     * @return list<ForecastObligation>
     */
    private function loanObligations(User $user, Collection $accounts, Carbon $today, Carbon $until): array
    {
        $loans = Loan::query()->withoutUserScope()->where('user_id', $user->id)->get()->keyBy('id');

        if ($loans->isEmpty()) {
            return [];
        }

        $payments = LoanPayment::query()
            ->whereIn('loan_id', $loans->keys())
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $until->toDateString())
            ->where(fn ($query) => $query
                ->where('status', '!=', 'paid')
                ->orWhereDate('due_date', '>=', $today->copy()->subDays(self::PAID_LOOKBACK_DAYS)->toDateString()))
            ->get();

        $postings = $this->postingsBy('loan_payment_id', $payments->pluck('id'));
        $result = [];

        foreach ($payments as $payment) {
            $loan = $loans[$payment->loan_id];

            $obligation = $this->classify(
                type: ForecastObligation::TYPE_LOAN,
                sourceId: $payment->id,
                due: $payment->due_date,
                name: $loan->name ?? 'Loan installment',
                accountId: $loan->account_id,
                creditCardId: null,
                accounts: $accounts,
                faceMinor: ForecastObligation::toMinor($payment->amount),
                postings: $postings->get($payment->id, collect()),
                today: $today,
                paid: $payment->status === LoanPaymentStatus::PAID,
            );

            if ($obligation) {
                $result[] = $obligation;
            }
        }

        return $result;
    }

    /**
     * @param Collection<int, CreditCard> $cards
     * @param Collection<int, Account> $accounts
     * @return list<ForecastObligation>
     */
    private function cardPaymentObligations(Collection $cards, Collection $accounts, Carbon $today, Carbon $until): array
    {
        if ($cards->isEmpty()) {
            return [];
        }

        $payments = CreditCardPayment::query()
            ->whereIn('credit_card_id', $cards->filter(fn (CreditCard $c) => ! $c->trashed())->keys())
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $until->toDateString())
            ->where(fn ($query) => $query
                ->where('status', '!=', 'paid')
                ->orWhereDate('due_date', '>=', $today->copy()->subDays(self::PAID_LOOKBACK_DAYS)->toDateString()))
            ->get();

        $postings = $this->postingsBy('credit_card_payment_id', $payments->pluck('id'));
        $result = [];

        foreach ($payments as $payment) {
            $card = $cards[$payment->credit_card_id];

            $obligation = $this->classify(
                type: ForecastObligation::TYPE_CREDIT_CARD,
                sourceId: $payment->id,
                due: $payment->due_date,
                name: $card->name ?? 'Credit card payment',
                accountId: $card->account_id,
                creditCardId: $card->id,
                accounts: $accounts,
                faceMinor: ForecastObligation::toMinor($payment->total_amount),
                postings: $postings->get($payment->id, collect()),
                today: $today,
                paid: $payment->status === CreditCardPaymentStatus::PAID,
            );

            if ($obligation) {
                $result[] = $obligation;
            }
        }

        return $result;
    }

    /**
     * @param Collection<int, Account> $accounts
     * @param Collection<int, CreditCard> $cards
     * @return list<ForecastObligation>
     */
    private function subscriptionObligations(User $user, Collection $accounts, Collection $cards, Carbon $today, Carbon $until): array
    {
        $subscriptions = Subscription::query()->withoutUserScope()
            ->with('frequencyOption')
            ->active()
            ->where('user_id', $user->id)
            ->whereNotNull('next_renewal_date')
            ->get();

        if ($subscriptions->isEmpty()) {
            return [];
        }

        $ids = $subscriptions->pluck('id');

        // Keyed by "subscription|renewal date": the renewal identity shared by both posting paths.
        $accountPostings = Transaction::query()->withoutUserScope()->withTrashed()
            ->whereIn('subscription_id', $ids)
            ->whereNotNull('subscription_renewal_date')
            ->get()
            ->groupBy(fn (Transaction $t) => $t->subscription_id . '|' . $t->subscription_renewal_date->toDateString());

        $cardPostings = CreditCardExpense::query()
            ->whereIn('subscription_id', $ids)
            ->whereNotNull('subscription_renewal_date')
            ->get()
            ->groupBy(fn (CreditCardExpense $e) => $e->subscription_id . '|' . Carbon::parse($e->subscription_renewal_date)->toDateString());

        $result = [];

        foreach ($subscriptions as $subscription) {
            $card = $subscription->credit_card_id ? $cards->get($subscription->credit_card_id) : null;
            $accountId = $subscription->credit_card_id ? $card?->account_id : $subscription->account_id;
            $faceMinor = ForecastObligation::toMinor($this->subscriptionService->getBillingAmount($subscription));

            $date = Carbon::parse($subscription->next_renewal_date)->startOfDay();

            for ($step = 0; $step < self::MAX_PROJECTION_STEPS && $date->lte($until); $step++) {
                $key = $subscription->id . '|' . $date->toDateString();
                $obligation = $this->classifySubscriptionOccurrence(
                    $subscription, $card, $accountId, $accounts, $date, $faceMinor, $today,
                    $accountPostings->get($key, collect()), $cardPostings->has($key),
                );

                if ($obligation) {
                    $result[] = $obligation;
                }

                $date = $this->subscriptionService->calculateNextRenewalDate($subscription, $date)->startOfDay();
            }
        }

        return $result;
    }

    /**
     * @param Collection<int, Account> $accounts
     * @param Collection<int, Transaction> $accountPostings
     */
    private function classifySubscriptionOccurrence(
        Subscription $subscription,
        ?CreditCard $card,
        ?int $accountId,
        Collection $accounts,
        Carbon $date,
        int $faceMinor,
        Carbon $today,
        Collection $accountPostings,
        bool $cardPosted,
    ): ?ForecastObligation {
        $overdue = $date->lt($today);
        $onCard = (bool) $subscription->credit_card_id;
        $liveAccountPosting = $accountPostings->contains(fn (Transaction $t) => ! $t->trashed());

        [$currency, $sourceReason] = $this->sourceFacts($accountId, $accounts);

        $make = fn (string $state, int $cash, ?string $reason) => new ForecastObligation(
            ForecastObligation::TYPE_SUBSCRIPTION, $subscription->id, $date->toDateString(), $subscription->name,
            $accountId, $subscription->credit_card_id, $currency, $faceMinor, $cash, $state, $reason, $overdue,
        );

        if ($sourceReason !== null) {
            return $make(ForecastObligation::STATE_UNRESOLVED, 0, $sourceReason);
        }

        // The renewal may have been posted on the other path after the billing source changed.
        if ($liveAccountPosting) {
            return $overdue ? null : $make(ForecastObligation::STATE_POSTED, 0, ForecastObligation::REASON_ALREADY_IN_BALANCE);
        }

        if ($cardPosted) {
            return $overdue ? null : $make(ForecastObligation::STATE_CARD_CHARGE, 0, ForecastObligation::REASON_CARD_CHARGE_RECORDED);
        }

        if ($onCard) {
            return $make(ForecastObligation::STATE_CARD_CHARGE, 0, ForecastObligation::REASON_CARD_CHARGE_PROJECTED);
        }

        $trashed = $accountPostings->isNotEmpty();

        if ($overdue) {
            return $make($trashed ? ForecastObligation::STATE_POSTING_TRASHED : ForecastObligation::STATE_UNPOSTED, 0, ForecastObligation::REASON_OVERDUE);
        }

        return $trashed
            ? $make(ForecastObligation::STATE_POSTING_TRASHED, $faceMinor, ForecastObligation::REASON_POSTING_SOFT_DELETED)
            : $make(ForecastObligation::STATE_UNPOSTED, $faceMinor, null);
    }

    /**
     * Shared classification for loan installments and card payments, which both post an
     * account transaction keyed by their own id.
     *
     * @param Collection<int, Account> $accounts
     * @param Collection<int, Transaction> $postings
     */
    private function classify(
        string $type,
        int $sourceId,
        CarbonInterface $due,
        string $name,
        ?int $accountId,
        ?int $creditCardId,
        Collection $accounts,
        int $faceMinor,
        Collection $postings,
        Carbon $today,
        bool $paid = false,
    ): ?ForecastObligation {
        $dueDate = Carbon::instance($due)->startOfDay();
        $overdue = $dueDate->lt($today);
        $hasLivePosting = $postings->contains(fn (Transaction $t) => ! $t->trashed());

        // A paid row with its posting is settled history, not an obligation.
        if ($paid && $hasLivePosting) {
            return null;
        }

        [$currency, $sourceReason] = $this->sourceFacts($accountId, $accounts);

        $make = fn (string $state, int $cash, ?string $reason) => new ForecastObligation(
            $type, $sourceId, $dueDate->toDateString(), $name, $accountId, $creditCardId,
            $currency, $faceMinor, $cash, $state, $reason, $overdue,
        );

        if ($sourceReason !== null) {
            return $make(ForecastObligation::STATE_UNRESOLVED, 0, $sourceReason);
        }

        // Marked paid but the debit never reached (or was removed from) the account: the balance
        // may not reflect it, which the forecast cannot resolve, so it warns instead of guessing.
        if ($paid) {
            return $make($postings->isNotEmpty() ? ForecastObligation::STATE_POSTING_TRASHED : ForecastObligation::STATE_UNPOSTED, 0, ForecastObligation::REASON_PAID_WITHOUT_POSTING);
        }

        // The posting, not the payment status, decides whether the balance already moved.
        if ($hasLivePosting) {
            return $overdue ? null : $make(ForecastObligation::STATE_POSTED, 0, ForecastObligation::REASON_ALREADY_IN_BALANCE);
        }

        $trashed = $postings->isNotEmpty();

        if ($overdue) {
            return $make($trashed ? ForecastObligation::STATE_POSTING_TRASHED : ForecastObligation::STATE_UNPOSTED, 0, ForecastObligation::REASON_OVERDUE);
        }

        return $trashed
            ? $make(ForecastObligation::STATE_POSTING_TRASHED, $faceMinor, ForecastObligation::REASON_POSTING_SOFT_DELETED)
            : $make(ForecastObligation::STATE_UNPOSTED, $faceMinor, null);
    }

    /**
     * @param Collection<int, Account> $accounts
     * @return array{0: ?string, 1: ?string} currency (null when unusable) and the reason it is unusable
     */
    private function sourceFacts(?int $accountId, Collection $accounts): array
    {
        $account = $accountId ? $accounts->get($accountId) : null;

        if ($account === null) {
            return [null, ForecastObligation::REASON_SOURCE_UNAVAILABLE];
        }

        $currency = strtoupper(trim((string) $account->currency));

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            return [null, ForecastObligation::REASON_UNKNOWN_CURRENCY];
        }

        return [$currency, null];
    }

    /**
     * @param Collection<int, int> $ids
     * @return Collection<int, Collection<int, Transaction>>
     */
    private function postingsBy(string $column, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return Transaction::query()->withoutUserScope()->withTrashed()
            ->whereIn($column, $ids)
            ->get()
            ->groupBy($column);
    }
}

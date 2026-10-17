<?php

namespace App\Services\Notifications;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\LoanPaymentStatus;
use App\Enums\LoanStatus;
use App\Enums\NotificationSeverity;
use App\Enums\SubscriptionStatus;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\LoanPayment;
use App\Models\Subscription;
use App\Services\BudgetService;
use App\Services\SubscriptionService;
use Carbon\CarbonInterface;

/**
 * Evaluates the four agreed finance triggers for one explicit owner. Read-only:
 * it never relies on ambient authentication and never writes notifications.
 */
class FinanceNotificationEvaluator
{
    public const TRIGGER_BUDGET = 'budget';

    public const TRIGGER_CARD_LIMIT = 'credit_card_limit';

    public const TRIGGER_CARD_PAYMENT = 'credit_card_payment';

    public const TRIGGER_LOAN_PAYMENT = 'loan_payment';

    public const TRIGGER_SUBSCRIPTION = 'subscription_renewal';

    public const TRIGGERS = [
        self::TRIGGER_BUDGET,
        self::TRIGGER_CARD_LIMIT,
        self::TRIGGER_CARD_PAYMENT,
        self::TRIGGER_LOAN_PAYMENT,
        self::TRIGGER_SUBSCRIPTION,
    ];

    private const CARD_LIMIT_RATIO = 0.90;

    private const LOOKAHEAD_DAYS = 3;

    public function __construct(
        private readonly BudgetService $budgets,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * @return list<ConditionSpec>
     */
    public function evaluate(int $userId, CarbonInterface $date): array
    {
        $today = $date->copy()->startOfDay();

        return [
            ...$this->budgetConditions($userId, $today),
            ...$this->cardLimitConditions($userId),
            ...$this->cardPaymentConditions($userId, $today),
            ...$this->loanPaymentConditions($userId, $today),
            ...$this->subscriptionConditions($userId, $today),
        ];
    }

    /**
     * @return list<ConditionSpec>
     */
    private function budgetConditions(int $userId, CarbonInterface $today): array
    {
        $overview = $this->budgets->getMonthlyOverview($userId, $today->year, $today->month);
        $period = $today->format('Y-m');
        $specs = [];

        foreach ($overview['categories'] as $category) {
            $severity = NotificationSeverity::tryFrom((string) $category['alert_status']);

            if ($severity === null) {
                continue;
            }

            $specs[] = new ConditionSpec(
                self::TRIGGER_BUDGET,
                'transaction_category',
                (int) $category['transaction_category_id'],
                $period,
                $severity,
                'Budget alert: '.$category['name'],
                sprintf(
                    '%s has used %.0f%% of its %s budget (%s of %s).',
                    $category['name'],
                    ($category['usage_ratio'] ?? 0) * 100,
                    $period,
                    $this->money($category['spent_amount']),
                    $this->money($category['budget_amount']),
                ),
                '/home',
            );
        }

        return $specs;
    }

    /**
     * @return list<ConditionSpec>
     */
    private function cardLimitConditions(int $userId): array
    {
        $cards = CreditCard::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('status', CreditCardStatus::ACTIVE)
            ->where('credit_limit', '>', 0)
            ->get();

        return $cards
            ->filter(fn (CreditCard $card) => (float) $card->current_balance >= (float) $card->credit_limit * self::CARD_LIMIT_RATIO - 1e-9)
            ->map(fn (CreditCard $card) => new ConditionSpec(
                self::TRIGGER_CARD_LIMIT,
                'credit_card',
                $card->id,
                'open',
                NotificationSeverity::WARNING,
                'Credit limit alert: '.$card->name,
                sprintf(
                    '%s is at %.0f%% of its credit limit (%s of %s).',
                    $card->name,
                    (float) $card->current_balance / (float) $card->credit_limit * 100,
                    $this->money($card->current_balance),
                    $this->money($card->credit_limit),
                ),
                '/credit-cards/'.$card->id,
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<ConditionSpec>
     */
    private function cardPaymentConditions(int $userId, CarbonInterface $today): array
    {
        $payments = CreditCardPayment::query()
            ->with('creditCard')
            ->where('status', CreditCardPaymentStatus::PENDING)
            ->whereDate('due_date', '<=', $today->toDateString())
            ->whereHas('creditCard', fn ($query) => $query
                ->withoutUserScope()
                ->where('user_id', $userId)
                ->where('status', CreditCardStatus::ACTIVE))
            ->get();

        return $payments->map(function (CreditCardPayment $payment) use ($today) {
            $overdue = $payment->due_date->startOfDay()->lt($today);

            return new ConditionSpec(
                self::TRIGGER_CARD_PAYMENT,
                'credit_card_payment',
                $payment->id,
                $payment->due_date->toDateString(),
                $overdue ? NotificationSeverity::CRITICAL : NotificationSeverity::WARNING,
                ($overdue ? 'Overdue card payment: ' : 'Card payment due today: ').$payment->creditCard->name,
                sprintf(
                    'A payment of %s for %s %s %s.',
                    $this->money($payment->total_amount),
                    $payment->creditCard->name,
                    $overdue ? 'was due on' : 'is due on',
                    $payment->due_date->toDateString(),
                ),
                '/credit-cards/'.$payment->credit_card_id,
            );
        })->values()->all();
    }

    /**
     * @return list<ConditionSpec>
     */
    private function loanPaymentConditions(int $userId, CarbonInterface $today): array
    {
        $payments = LoanPayment::query()
            ->with('loan')
            ->where('status', LoanPaymentStatus::PENDING)
            ->whereDate('due_date', '<=', $today->copy()->addDays(self::LOOKAHEAD_DAYS)->toDateString())
            ->whereHas('loan', fn ($query) => $query
                ->withoutUserScope()
                ->where('user_id', $userId)
                ->where('status', LoanStatus::ACTIVE))
            ->get();

        return $payments->map(function (LoanPayment $payment) use ($today) {
            $overdue = $payment->due_date->startOfDay()->lt($today);

            return new ConditionSpec(
                self::TRIGGER_LOAN_PAYMENT,
                'loan_payment',
                $payment->id,
                $payment->due_date->toDateString(),
                $overdue ? NotificationSeverity::CRITICAL : NotificationSeverity::WARNING,
                ($overdue ? 'Overdue loan installment: ' : 'Loan installment due soon: ').$payment->loan->name,
                sprintf(
                    'An installment of %s for %s %s %s.',
                    $this->money($payment->amount),
                    $payment->loan->name,
                    $overdue ? 'was due on' : 'is due on',
                    $payment->due_date->toDateString(),
                ),
                '/loans/'.$payment->loan_id,
            );
        })->values()->all();
    }

    /**
     * @return list<ConditionSpec>
     */
    private function money(float|string|null $amount): string
    {
        // number_format is locale-independent, unlike sprintf's %f (which follows the process locale).
        return number_format((float) $amount, 2, '.', '');
    }

    private function subscriptionConditions(int $userId, CarbonInterface $today): array
    {
        $subscriptions = Subscription::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->whereDate('next_renewal_date', '>=', $today->toDateString())
            ->whereDate('next_renewal_date', '<=', $today->copy()->addDays(self::LOOKAHEAD_DAYS)->toDateString())
            ->get();

        return $subscriptions->map(fn (Subscription $subscription) => new ConditionSpec(
            self::TRIGGER_SUBSCRIPTION,
            'subscription',
            $subscription->id,
            $subscription->next_renewal_date->toDateString(),
            NotificationSeverity::WARNING,
            'Subscription renewal: '.$subscription->name,
            sprintf(
                '%s renews on %s (%s).',
                $subscription->name,
                $subscription->next_renewal_date->toDateString(),
                $this->money($this->subscriptions->getBillingAmount($subscription)),
            ),
            '/subscriptions',
        ))->values()->all();
    }
}

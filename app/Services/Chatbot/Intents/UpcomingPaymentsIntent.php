<?php

namespace App\Services\Chatbot\Intents;

use App\Models\User;
use App\Services\Chatbot\Concerns\ResolvesUserCurrency;
use App\Services\Chatbot\Contracts\ChatIntent;
use App\Services\UpcomingPaymentsService;

class UpcomingPaymentsIntent implements ChatIntent
{
    use ResolvesUserCurrency;

    private const TYPE_LABELS = [
        'loan' => 'Loan',
        'credit-card' => 'Credit card',
        'subscription' => 'Subscription',
    ];

    public function __construct(private readonly UpcomingPaymentsService $upcomingPaymentsService) {}

    public function key(): string
    {
        return 'upcoming_payments';
    }

    public function handle(User $user, array $params): array
    {
        $days = (int) ($params['days'] ?? 3);
        $currency = $this->resolveUserCurrency($user);

        $payments = $this->upcomingPaymentsService->forUser($user, $days);

        $items = collect($payments)->map(fn (array $payment) => [
            'label' => (string) $payment['description'],
            'value' => round((float) $payment['amount'], 2),
            'currency' => $currency,
            'detail' => sprintf(
                '%s · due %s',
                self::TYPE_LABELS[$payment['type']] ?? ucfirst((string) $payment['type']),
                (string) $payment['due_date'],
            ),
        ])->values()->all();

        $window = $days === 1 ? 'the next day' : "the next {$days} days";

        return [
            'intent' => $this->key(),
            'headline' => "Here's what's due in {$window}.",
            'highlight' => $items === [] ? null : [
                'label' => 'Total due',
                'value' => round(collect($items)->sum('value'), 2),
                'currency' => $currency,
            ],
            'items' => $items,
            'empty_message' => $items === [] ? "Nothing is due in {$window}." : null,
        ];
    }
}

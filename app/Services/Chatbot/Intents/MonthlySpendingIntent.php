<?php

namespace App\Services\Chatbot\Intents;

use App\Models\User;
use App\Services\Chatbot\Concerns\ResolvesUserCurrency;
use App\Services\Chatbot\Contracts\ChatIntent;
use App\Services\FinanceReportService;
use Carbon\Carbon;

class MonthlySpendingIntent implements ChatIntent
{
    use ResolvesUserCurrency;

    public function __construct(private readonly FinanceReportService $financeReportService) {}

    public function key(): string
    {
        return 'monthly_spending';
    }

    public function handle(User $user, array $params): array
    {
        $month = isset($params['month']) && $params['month'] !== ''
            ? Carbon::createFromFormat('Y-m', (string) $params['month'])->startOfMonth()
            : now()->startOfMonth();

        $userId = $user->hasRole('superadmin') ? null : $user->id;

        $table = $this->financeReportService->getTable($month->year, $userId);
        $row = $table[$month->month - 1];

        $currency = $this->resolveUserCurrency($user);

        return [
            'intent' => $this->key(),
            'headline' => "Here's your {$month->format('F Y')} summary.",
            'highlight' => [
                'label' => 'Net',
                'value' => round((float) $row->net, 2),
                'currency' => $currency,
            ],
            'items' => [
                ['label' => 'Earnings', 'value' => round((float) $row->earnings, 2), 'currency' => $currency, 'detail' => null],
                ['label' => 'Expenses', 'value' => round((float) $row->expenses, 2), 'currency' => $currency, 'detail' => null],
                ['label' => 'Net', 'value' => round((float) $row->net, 2), 'currency' => $currency, 'detail' => null],
            ],
            'empty_message' => null,
        ];
    }
}

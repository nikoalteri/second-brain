<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\CreditCardPayment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class UpcomingPaymentsWidget extends ChartWidget
{
    public function getHeading(): ?string
    {
        return 'Upcoming Payments (Next 7 Days)';
    }

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $user = Auth::user();
        $today = now()->toDateString();
        $nextWeek = now()->addDays(7)->toDateString();

        // Loan payments due
        $loanPayments = LoanPayment::whereHas('loan', fn($q) => $q->where('user_id', $user->id))
            ->whereBetween('due_date', [$today, $nextWeek])
            ->where('status', '!=', 'paid')
            ->get();

        // Credit card payments due
        $ccPayments = CreditCardPayment::whereHas('creditCard', fn($q) => $q->where('user_id', $user->id))
            ->whereBetween('due_date', [$today, $nextWeek])
            ->where('status', '!=', 'paid')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Loan Payments',
                    'data' => [$loanPayments->sum('amount')],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                ],
                [
                    'label' => 'CC Payments',
                    'data' => [$ccPayments->sum('total_amount')],
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
            ],
            'labels' => ['Upcoming'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

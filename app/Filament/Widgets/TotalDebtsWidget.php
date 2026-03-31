<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use App\Models\CreditCard;
use App\Enums\CreditCardPaymentStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TotalDebtsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'half';

    protected function getStats(): array
    {
        $user = Auth::user();

        // Loans remaining amount
        $loansDebt = Loan::where('user_id', $user->id)
            ->where('status', '!=', 'paid')
            ->sum('remaining_amount');

        // Credit cards current balance
        $cardsDebt = CreditCard::where('user_id', $user->id)
            ->sum('current_balance');

        $totalDebt = (float) $loansDebt + (float) $cardsDebt;

        return [
            Stat::make('Total Debts', '€' . number_format($totalDebt, 2, ',', '.'))
                ->description('Loans + Credit Cards')
                ->color('danger')
                ->icon('heroicon-o-arrow-trending-down'),
        ];
    }
}

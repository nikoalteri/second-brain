<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Loan;
use App\Models\CreditCard;
use App\Enums\CreditCardPaymentStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class NetWorthWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'half';

    protected function getStats(): array
    {
        $user = Auth::user();

        // Sum of active accounts (non-debt)
        $netWorth = Account::where('user_id', $user->id)
            ->whereNotIn('type', ['debt', 'credit_card'])
            ->sum('balance');

        return [
            Stat::make('Net Worth', '€' . number_format((float) $netWorth, 2, ',', '.'))
                ->description('All active accounts (excl. debt)')
                ->color('success')
                ->icon('heroicon-o-trending-up'),
        ];
    }
}

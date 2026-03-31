<?php

namespace App\Filament\Widgets;

use App\Models\LoanPayment;
use App\Models\CreditCardPayment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UpcomingPaymentsWidget extends BaseWidget
{
    public function getHeading(): ?string
    {
        return 'Upcoming Payments (Next 7 Days)';
    }

    protected int|string|array $columnSpan = 'half';

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $today = now();
        $nextWeek = now()->addDays(7);

        // Get loan payments
        $loanPayments = LoanPayment::whereHas('loan', fn($q) => $q->where('user_id', $user->id))
            ->whereBetween('due_date', [$today, $nextWeek])
            ->where('status', '!=', 'paid')
            ->get()
            ->map(fn($p) => [
                'id' => 'loan_' . $p->id,
                'type' => 'Loan',
                'description' => $p->loan->name ?? 'Loan Payment',
                'amount' => $p->amount,
                'due_date' => $p->due_date,
                'status' => $p->status,
            ]);

        // Get credit card payments
        $ccPayments = CreditCardPayment::whereHas('creditCard', fn($q) => $q->where('user_id', $user->id))
            ->whereBetween('due_date', [$today, $nextWeek])
            ->where('status', '!=', 'paid')
            ->get()
            ->map(fn($p) => [
                'id' => 'cc_' . $p->id,
                'type' => 'Credit Card',
                'description' => $p->creditCard->name ?? 'CC Payment',
                'amount' => $p->total_amount,
                'due_date' => $p->due_date,
                'status' => $p->status,
            ]);

        // Merge and sort by due_date
        $allPayments = collect($loanPayments)
            ->merge($ccPayments)
            ->sortBy('due_date')
            ->values();

        return $table
            ->query(
                CreditCardPayment::query()
                    ->whereHas('creditCard', fn($q) => $q->where('user_id', $user->id))
                    ->whereBetween('due_date', [$today, $nextWeek])
                    ->where('status', '!=', 'paid')
            )
            ->columns([
                TextColumn::make('creditCard.name')
                    ->label('Payment Type')
                    ->default('Credit Card Payment'),

                TextColumn::make('total_amount')
                    ->money('EUR')
                    ->label('Amount'),

                TextColumn::make('due_date')
                    ->date()
                    ->label('Due Date')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status'),
            ])
            ->paginationPageOptions([25])
            ->defaultPaginationPageOption(25);
    }
}

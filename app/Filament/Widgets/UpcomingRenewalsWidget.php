<?php

namespace App\Filament\Widgets;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UpcomingRenewalsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Subscription::query()
                    ->where('user_id', Auth::id())
                    ->where('status', SubscriptionStatus::ACTIVE)
                    ->whereBetween('next_renewal_date', [now(), now()->addDays(30)])
                    ->orderBy('next_renewal_date')
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->label('Subscription'),

                BadgeColumn::make('frequency')
                    ->label('Frequency'),

                TextColumn::make('monthly_cost')
                    ->money('EUR')
                    ->label('Monthly Cost'),

                TextColumn::make('next_renewal_date')
                    ->date()
                    ->label('Next Renewal')
                    ->sortable(),
            ]);
    }
}

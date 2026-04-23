<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AccountsListWidget extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected null|string $pollingInterval = null;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getAccountsQuery())
            ->paginated(false)
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Account')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'bank' => 'primary',
                        'cash' => 'warning',
                        'investment' => 'success',
                        'debt' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('signed_balance')
                    ->label('Balance')
                    ->money('EUR')
                    ->alignEnd()
                    ->sortable()
                    ->color(fn(float|int|string $state): string => ((float) $state) >= 0 ? 'success' : 'danger'),
            ]);
    }

    protected function getAccountsQuery(): Builder
    {
        return Account::query()->where('user_id', Auth::id());
    }
}

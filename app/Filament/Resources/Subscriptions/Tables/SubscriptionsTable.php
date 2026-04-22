<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Enums\SubscriptionStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('frequencyOption.name')
                    ->label('Frequency')
                    ->sortable(),

                TextColumn::make('annual_cost')
                    ->label('Renewal amount')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('payment_source_type')
                    ->label('Source')
                    ->formatStateUsing(fn (?string $state, $record) => match ($state) {
                        'account' => $record->account?->name ?? 'Account',
                        'credit-card' => $record->creditCard?->name ?? 'Credit card',
                        default => 'Not set',
                    }),

                TextColumn::make('next_renewal_date')
                    ->date()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SubscriptionStatus::class),

                SelectFilter::make('subscription_frequency_id')
                    ->relationship('frequencyOption', 'name'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

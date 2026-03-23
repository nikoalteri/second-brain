<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionFrequency;
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

                BadgeColumn::make('frequency')
                    ->sortable(),

                TextColumn::make('monthly_cost')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('next_renewal_date')
                    ->date()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SubscriptionStatus::class),

                SelectFilter::make('frequency')
                    ->options(SubscriptionFrequency::class),
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

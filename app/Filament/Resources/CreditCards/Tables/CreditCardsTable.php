<?php

namespace App\Filament\Resources\CreditCards\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CreditCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable(),
                TextColumn::make('credit_limit')
                    ->label('Credit limit')
                    ->formatStateUsing(fn($state) => $state === null ? 'Unlimited' : number_format((float) $state, 2) . ' EUR')
                    ->sortable(),
                TextColumn::make('current_balance')
                    ->label('Used credit')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('available_credit')
                    ->label('Available credit')
                    ->getStateUsing(fn($record) => $record->available_credit)
                    ->formatStateUsing(fn($state) => $state === null ? 'Unlimited' : number_format((float) $state, 2) . ' EUR'),
                TextColumn::make('fixed_payment')
                    ->label('Fixed installment')
                    ->money('EUR')
                    ->toggleable(),
                TextColumn::make('interest_rate')
                    ->label('Rate')
                    ->formatStateUsing(fn($state) => $state !== null ? number_format((float) $state, 2) . '%' : '-')
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}

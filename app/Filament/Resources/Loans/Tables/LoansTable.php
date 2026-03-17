<?php

namespace App\Filament\Resources\Loans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->where('user_id', auth()->id()))

            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable(),
                TextColumn::make('monthly_payment')
                    ->label('Monthly Payment')
                    ->money('EUR', locale: 'it'),
                TextColumn::make('remaining_amount')
                    ->label('Remaining Amount')
                    ->money('EUR', locale: 'it')
                    ->sortable(),
                TextColumn::make('withdrawal_day')
                    ->label('Withdrawal Day'),
                TextColumn::make('paid_installments')
                    ->label('Paid Installments')
                    ->formatStateUsing(fn($state, $record) => "{$state}/{$record->total_installments}"),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date('d/m/Y')
                    ->label('End Date')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'defaulted' => 'Defaulted',
                    ])
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('end_date');
    }
}

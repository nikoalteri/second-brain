<?php

namespace App\Filament\Resources\Transactions\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use Illuminate\Support\Number;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type.name')
                    ->label('Type')
                    ->badge()
                    ->color(fn($record) => match ($record->type?->name) {
                        'Earnings' => 'success',
                        'Expenses' => 'danger',
                        'Transfer' => 'info',
                        'Cashback' => 'warning',
                        default    => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('toAccount.name')
                    ->label('Destination Account')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->category?->parent
                            ? $record->category->parent->name . ' › ' . $record->category->name
                            : $record->category?->name
                    )
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn($state) => Number::currency($state, 'EUR', locale: 'it'))
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('competence_month')
                    ->label('Competence Month')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('date')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('to')->label('To'),
                    ])
                    ->query(function ($query, array $data) {
                        $query
                            ->when($data['from'], fn($q) => $q->whereDate('date', '>=', $data['from']))
                            ->when($data['to'],   fn($q) => $q->whereDate('date', '<=', $data['to']));
                    }),

                SelectFilter::make('transaction_type_id')
                    ->label('Type')
                    ->relationship('type', 'name'),

                SelectFilter::make('account_id')
                    ->label('Account')
                    ->relationship('account', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Action::make('report')
                    ->label('Report Finance')
                    ->icon('heroicon-o-chart-bar')
                    ->color('primary')
                    ->url('/admin/finance-report'),
            ]);
    }
}

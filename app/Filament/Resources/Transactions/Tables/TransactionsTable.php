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
use Illuminate\Support\Number;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('account.name')
                    ->label('Conto')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type.name')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($record) => match ($record->type?->name) {
                        'Entrate'  => 'success',
                        'Uscite'   => 'danger',
                        'Trasferimento' => 'info',
                        'Cashback' => 'warning',
                        default    => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('toAccount.name')
                    ->label('Conto Destinazione')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->formatStateUsing(
                        fn($record) =>
                        $record->category?->parent
                            ? $record->category->parent->name . ' › ' . $record->category->name
                            : $record->category?->name
                    )
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Descrizione')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('amount')
                    ->label('Importo')
                    ->formatStateUsing(fn($state) => Number::currency($state, 'EUR', locale: 'it'))
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('competence_month')
                    ->label('Competenza')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('date')
                    ->form([
                        DatePicker::make('from')->label('Da'),
                        DatePicker::make('to')->label('A'),
                    ])
                    ->query(function ($query, array $data) {
                        $query
                            ->when($data['from'], fn($q) => $q->whereDate('date', '>=', $data['from']))
                            ->when($data['to'],   fn($q) => $q->whereDate('date', '<=', $data['to']));
                    }),

                SelectFilter::make('transaction_type_id')
                    ->label('Tipo')
                    ->relationship('type', 'name'),

                SelectFilter::make('account_id')
                    ->label('Conto')
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
            ]);
    }
}

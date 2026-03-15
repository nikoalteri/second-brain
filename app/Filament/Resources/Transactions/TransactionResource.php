<?php

namespace App\Filament\Resources\Transactions;

use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\Pages\ViewTransaction;
use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Resources\Transactions\Schemas\TransactionInfolist;
use App\Filament\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Forms\Components\DatePicker;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Transazioni';

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return TransactionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->when(auth()->user()->id, fn($q) => $q->where('user_id', auth()->user()->id));
    }

    public static function getTableFilters(): array
    {
        return [
            // ✅ Filtro per Tipo
            SelectFilter::make('type')
                ->relationship('type', 'name')
                ->multiple()
                ->preload(),

            // ✅ Filtro per Categoria
            SelectFilter::make('category')
                ->relationship('category', 'name')
                ->multiple()
                ->preload(),

            // ✅ Filtro per Account
            SelectFilter::make('account')
                ->relationship('account', 'name')
                ->multiple()
                ->preload(),

            // ✅ Filtro per Periodo
            Filter::make('date')
                ->form([
                    DatePicker::make('started_from')->label('Da'),
                    DatePicker::make('started_until')->label('A'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['started_from'],
                            fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                        )
                        ->when(
                            $data['started_until'],
                            fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                        );
                }),

            // ✅ Filtro per Solo Transfer
            TernaryFilter::make('is_transfer')
                ->label('Solo Transfer')
                ->placeholder('Tutti')
                ->trueLabel('Solo Transfer')
                ->falseLabel('Esclusi Transfer'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'view'   => ViewTransaction::route('/{record}'),
            'edit'   => EditTransaction::route('/{record}/edit'),
        ];
    }
}

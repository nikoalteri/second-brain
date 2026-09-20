<?php

namespace App\Filament\Resources\TransactionTypes;

use App\Filament\Resources\TransactionTypes\Pages\ListTransactionTypes;
use App\Filament\Resources\TransactionTypes\Tables\TransactionTypesTable;
use App\Models\TransactionType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TransactionTypeResource extends Resource
{
    protected static ?string $model = TransactionType::class;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Transaction Type';

    protected static ?string $singularLabel = 'Transaction Type';

    protected static ?int $navigationOrder = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Transaction Types';

    public static function table(Table $table): Table
    {
        return TransactionTypesTable::configure($table);
    }

    /** Transaction types are a fixed lookup table: core behaviour (transfer detection,
     *  is_income) keys off exact names, so they're seeded once and never edited. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactionTypes::route('/'),
        ];
    }
}

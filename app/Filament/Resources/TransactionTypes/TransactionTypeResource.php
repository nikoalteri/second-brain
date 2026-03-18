<?php

namespace App\Filament\Resources\TransactionTypes;

use App\Filament\Resources\TransactionTypes\Pages\CreateTransactionType;
use App\Filament\Resources\TransactionTypes\Pages\EditTransactionType;
use App\Filament\Resources\TransactionTypes\Pages\ListTransactionTypes;
use App\Filament\Resources\TransactionTypes\Schemas\TransactionTypeForm;
use App\Filament\Resources\TransactionTypes\Tables\TransactionTypesTable;
use App\Models\TransactionType;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TransactionTypeResource extends Resource
{
    protected static ?string $model = TransactionType::class;
    protected static string|UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Transaction Type';
    protected static ?string $singularLabel = 'Transaction Type';
    protected static ?int $navigationOrder = 1;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $recordTitleAttribute = 'Transaction Types';

    public static function form(Schema $schema): Schema
    {
        return TransactionTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionTypesTable::configure($table);
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
            'create' => CreateTransactionType::route('/create'),
            'edit' => EditTransactionType::route('/{record}/edit'),
        ];
    }
}

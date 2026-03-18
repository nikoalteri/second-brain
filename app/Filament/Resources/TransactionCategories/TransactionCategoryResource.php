<?php

namespace App\Filament\Resources\TransactionCategories;

use App\Filament\Resources\TransactionCategories\Pages\CreateTransactionCategory;
use App\Filament\Resources\TransactionCategories\Pages\EditTransactionCategory;
use App\Filament\Resources\TransactionCategories\Pages\ListTransactionCategories;
use App\Filament\Resources\TransactionCategories\Pages\ViewTransactionCategory;
use App\Filament\Resources\TransactionCategories\Schemas\TransactionCategoryForm;
use App\Filament\Resources\TransactionCategories\Schemas\TransactionCategoryInfolist;
use App\Filament\Resources\TransactionCategories\Tables\TransactionCategoriesTable;
use App\Models\TransactionCategory;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TransactionCategoryResource extends Resource
{
    protected static ?string $model = TransactionCategory::class;
    protected static string|UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Transaction Category';
    protected static ?string $singularLabel = 'Transaction Category';
    protected static ?int $navigationOrder = 1;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $recordTitleAttribute = 'transaction Categories';

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return TransactionCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransactionCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionCategoriesTable::configure($table);
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
            'index' => ListTransactionCategories::route('/'),
            'create' => CreateTransactionCategory::route('/create'),
            'view' => ViewTransactionCategory::route('/{record}'),
            'edit' => EditTransactionCategory::route('/{record}/edit'),
        ];
    }
}

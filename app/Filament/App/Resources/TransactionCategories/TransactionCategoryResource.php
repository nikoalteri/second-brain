<?php

namespace App\Filament\App\Resources\TransactionCategories;

use App\Filament\App\Resources\TransactionCategories\Pages\CreateTransactionCategory;
use App\Filament\App\Resources\TransactionCategories\Pages\EditTransactionCategory;
use App\Filament\App\Resources\TransactionCategories\Pages\ListTransactionCategories;
use App\Filament\App\Resources\TransactionCategories\Pages\ViewTransactionCategory;
use App\Filament\App\Resources\TransactionCategories\Schemas\TransactionCategoryForm;
use App\Filament\App\Resources\TransactionCategories\Schemas\TransactionCategoryInfolist;
use App\Filament\App\Resources\TransactionCategories\Tables\TransactionCategoriesTable;
use App\Models\TransactionCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TransactionCategoryResource extends Resource
{
    protected static ?string $model = TransactionCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Transaction Category';

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

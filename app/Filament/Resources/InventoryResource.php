<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Inventories\Pages\CreateInventory;
use App\Filament\Resources\Inventories\Pages\EditInventory;
use App\Filament\Resources\Inventories\Pages\ListInventories;
use App\Models\Inventory;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use UnitEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;
    protected static string|UnitEnum|null $navigationGroup = 'Home Management';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('property_id')
                ->relationship('property', 'address')
                ->required(),
            TextInput::make('name')
                ->required(),
            Textarea::make('description'),
            Select::make('inventory_category_id')
                ->relationship('category', 'name')
                ->required(),
            TextInput::make('value')
                ->numeric()
                ->prefix('$')
                ->required(),
            TextInput::make('location')
                ->required(),
            DatePicker::make('purchase_date'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('property.address')
                ->label('Property')
                ->searchable()
                ->limit(25),
            TextColumn::make('name')
                ->searchable(),
            TextColumn::make('category.name')
                ->label('Category'),
            TextColumn::make('value')
                ->money('USD'),
            TextColumn::make('location'),
            TextColumn::make('purchase_date')
                ->date('M d, Y'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventories::route('/'),
            'create' => CreateInventory::route('/create'),
            'edit' => EditInventory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::user()?->hasRole('superadmin')) {
            return $query;
        }

        return $query->where('user_id', Auth::id());
    }
}

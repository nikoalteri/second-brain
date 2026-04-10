<?php

namespace App\Filament\Resources;

use App\Models\InventoryCategory;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use UnitEnum;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InventoryCategoryResource extends Resource
{
    protected static ?string $model = InventoryCategory::class;
    protected static string|UnitEnum|null $navigationGroup = 'Home Management';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('depreciation_rate')
                ->numeric()
                ->suffix('%')
                ->required()
                ->step(0.01),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
            TextColumn::make('depreciation_rate')
                ->suffix('%'),
            TextColumn::make('inventories.count')
                ->label('Items')
                ->counts('inventories'),
        ])->actions([
            EditAction::make(),
            DeleteAction::make(),
        ])->headerActions([
            CreateAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [];
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

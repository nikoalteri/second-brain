<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class InventoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'inventories';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
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

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
            TextColumn::make('category.name')
                ->label('Category'),
            TextColumn::make('location'),
            TextColumn::make('value')
                ->money('USD'),
            TextColumn::make('purchase_date')
                ->date('M d, Y'),
        ])->filters([
            //
        ])->headerActions([
            CreateAction::make(),
        ])->actions([
            EditAction::make(),
            DeleteAction::make(),
        ]);
    }
}

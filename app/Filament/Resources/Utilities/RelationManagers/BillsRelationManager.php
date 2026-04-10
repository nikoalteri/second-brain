<?php

namespace App\Filament\Resources\Utilities\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BillsRelationManager extends RelationManager
{
    protected static string $relationship = 'utilityBills';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            DatePicker::make('date')
                ->required(),
            TextInput::make('reading')
                ->numeric(),
            TextInput::make('cost')
                ->numeric()
                ->prefix('$')
                ->required(),
            Textarea::make('notes'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('date')
                ->date('M d, Y'),
            TextColumn::make('reading'),
            TextColumn::make('cost')
                ->money('USD'),
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

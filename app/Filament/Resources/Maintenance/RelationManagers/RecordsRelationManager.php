<?php

namespace App\Filament\Resources\Maintenance\RelationManagers;

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

class RecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'propertyMaintenanceRecords';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            DatePicker::make('date')
                ->required(),
            TextInput::make('cost')
                ->numeric()
                ->prefix('$')
                ->required(),
            TextInput::make('contractor'),
            Textarea::make('notes'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('date')
                ->date('M d, Y'),
            TextColumn::make('cost')
                ->money('USD'),
            TextColumn::make('contractor'),
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

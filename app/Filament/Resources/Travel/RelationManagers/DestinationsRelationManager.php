<?php

namespace App\Filament\Resources\Travel\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DestinationsRelationManager extends RelationManager
{
    protected static string $relationship = 'destinations';

    protected static ?string $title = 'Destinations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Destination Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('country')
                    ->label('Country')
                    ->required()
                    ->maxLength(255),
                TextInput::make('timezone')
                    ->label('Timezone')
                    ->default('UTC')
                    ->required()
                    ->maxLength(50),
                TextInput::make('latitude')
                    ->label('Latitude')
                    ->numeric()
                    ->step(0.000001),
                TextInput::make('longitude')
                    ->label('Longitude')
                    ->numeric()
                    ->step(0.000001),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Destination')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('timezone')
                    ->label('Timezone')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}

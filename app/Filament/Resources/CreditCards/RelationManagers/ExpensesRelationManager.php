<?php

namespace App\Filament\Resources\CreditCards\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $title = 'Expenses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('spent_at')
                    ->label('Spent at')
                    ->required()
                    ->default(now()),
                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('€')
                    ->minValue(0.01)
                    ->required(),
                TextInput::make('description')
                    ->label('Description')
                    ->required()
                    ->maxLength(255),
                TextInput::make('notes')
                    ->label('Notes')
                    ->maxLength(255),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('spent_at', 'desc')
            ->columns([
                TextColumn::make('spent_at')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('cycle.period_month')
                    ->label('Cycle')
                    ->badge(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->toggleable()
                    ->limit(30),
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

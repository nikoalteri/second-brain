<?php

namespace App\Filament\Resources\SavingGoals\RelationManagers;

use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContributionsRelationManager extends RelationManager
{
    protected static string $relationship = 'contributions';

    protected static ?string $title = 'Contributions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required()
                    ->default(now()),
                TextInput::make('amount')
                    ->label('Amount')
                    ->helperText('Positive to deposit, negative to withdraw')
                    ->numeric()
                    ->prefix('€')
                    ->required(),
                Select::make('account_id')
                    ->label('From account (optional)')
                    ->relationship('account', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('notes')
                    ->label('Notes')
                    ->maxLength(255),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn () => Money::currency(), locale: fn () => Money::locale())
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('account.name')
                    ->label('Account')
                    ->placeholder('—'),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->toggleable()
                    ->limit(40),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}

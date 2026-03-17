<?php

namespace App\Filament\Resources\Loans\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Rate';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('due_date')
                    ->label('Scadenza prevista')
                    ->required(),

                DatePicker::make('actual_date')
                    ->label('Data pagamento'),

                TextInput::make('amount')
                    ->label('Importo')
                    ->numeric()
                    ->prefix('€')
                    ->required(),

                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'pagato' => 'Pagato',
                        'dapagare' => 'Da pagare',
                    ])
                    ->default('dapagare')
                    ->required(),

                TextInput::make('notes')
                    ->label('Note')
                    ->maxLength(255),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                TextColumn::make('due_date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('actual_date')
                    ->label('Pagata il')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pagato' => 'success',
                        'dapagare' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('notes')
                    ->label('Note')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('due_date');
    }
}

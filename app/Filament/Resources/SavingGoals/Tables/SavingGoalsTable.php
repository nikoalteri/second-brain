<?php

namespace App\Filament\Resources\SavingGoals\Tables;

use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class SavingGoalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('account.name')
                    ->label('Account')
                    ->searchable(),

                TextColumn::make('current_amount')
                    ->label('Saved')
                    ->state(fn ($record) => $record->current_amount)
                    ->formatStateUsing(fn ($state) => Number::currency($state, Money::currency(), Money::locale())),

                TextColumn::make('target_amount')
                    ->label('Target')
                    ->formatStateUsing(fn ($state) => Number::currency($state, Money::currency(), Money::locale()))
                    ->sortable(),

                TextColumn::make('progress_percent')
                    ->label('Progress')
                    ->state(fn ($record) => $record->progress_percent)
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1) . '%')
                    ->color(fn ($state) => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'warning',
                        default => 'gray',
                    })
                    ->badge(),

                TextColumn::make('target_date')
                    ->label('Target date')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

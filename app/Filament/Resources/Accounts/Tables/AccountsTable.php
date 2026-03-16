<?php

namespace App\Filament\Resources\Accounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'bank'           => 'primary',
                        'cash'           => 'warning',
                        'investment'     => 'success',
                        'debt'           => 'danger',
                        'emergency_fund' => 'gray',
                        default          => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'bank'           => 'Bancario',
                        'cash'           => 'Contanti',
                        'investment'     => 'Investimento',
                        'debt'           => 'Debito',
                        'emergency_fund' => 'Fondo Emergenza',
                        default          => $state,
                    }),

                TextColumn::make('opening_balance')
                    ->label('Saldo Iniziale')
                    ->formatStateUsing(fn($state) => Number::currency($state, 'EUR', locale: 'it'))
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('balance')
                    ->label('Saldo Attuale')
                    ->formatStateUsing(fn($state) => Number::currency($state, 'EUR', locale: 'it'))
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger'),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                IconColumn::make('is_debt')
                    ->label('Debito')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('color')
                    ->label('Colore')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('currency')
                    ->label('Valuta')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creato')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'bank'           => 'Bancario',
                        'cash'           => 'Contanti',
                        'investment'     => 'Investimento',
                        'emergency_fund' => 'Fondo Emergenza',
                        'debt'           => 'Debito',
                    ])
                    ->multiple(),

                SelectFilter::make('is_active')
                    ->label('Stato')
                    ->options([
                        1 => 'Attivo',
                        0 => 'Non attivo',
                    ]),

                SelectFilter::make('is_debt')
                    ->label('Tipo conto')
                    ->options([
                        1 => 'Debito',
                        0 => 'Non debito',
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

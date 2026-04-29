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
                    ->label('Name')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('type')
                    ->label('Type')
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
                        'bank' => 'Bank',
                        'cash' => 'Cash',
                        'investment' => 'Investment',
                        'debt' => 'Debt',
                        'emergency_fund' => 'Emergency Fund',
                        default          => $state,
                    }),

                TextColumn::make('opening_balance')
                    ->label('Opening Balance')
                    ->formatStateUsing(fn($state) => Number::currency($state, 'EUR', locale: 'it'))
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('balance')
                    ->label('Current Balance')
                    ->formatStateUsing(fn($state) => Number::currency($state, 'EUR', locale: 'it'))
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('currency')
                    ->label('Currency')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'bank'           => 'Bank',
                        'cash'           => 'Cash',
                        'investment'     => 'Investment',
                        'emergency_fund' => 'Emergency Fund',
                        'debt'           => 'Debt',
                    ])
                    ->multiple(),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
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

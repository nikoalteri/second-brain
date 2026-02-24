<?php

namespace App\Filament\Resources\Accounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->weight('medium'),
                BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'bank',
                        'warning' => 'cash',
                        'success' => 'investment',
                        'danger' => 'debt',
                        'gray' => 'emergency_fund',
                    ]),
                TextColumn::make('signed_balance')
                    ->money('EUR', locale: 'it')
                    ->sortable()
                    ->color(fn($state): string => $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('color')
                    ->searchable(),
                TextColumn::make('icon')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),
                IconColumn::make('is_debt')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'bank' => 'Bank',
                        'cash' => 'Cash',
                        'investment' => 'Investment',
                        'emergency_fund' => 'Emergency fund',
                        'debt' => 'Debt',
                    ])
                    ->multiple(),
                SelectFilter::make('is_active')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
                SelectFilter::make('is_debt')
                    ->options([
                        1 => 'Debt',
                        0 => 'Non-debt',
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

<?php

namespace App\Filament\Resources\Travel\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TripsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Trip')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('M d, Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('M d, Y')
                    ->sortable(),
                TextColumn::make('destination_count')
                    ->label('Destinations')
                    ->getStateUsing(fn($record) => $record->destinations()->count())
                    ->sortable(false),
                TextColumn::make('activity_count')
                    ->label('Activities')
                    ->getStateUsing(fn($record) => $record->activity_count)
                    ->sortable(false),
            ]);
    }
}

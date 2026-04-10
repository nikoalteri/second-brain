<?php

namespace App\Filament\Resources\Itinerary\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItinerariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),
                TextColumn::make('trip.title')
                    ->label('Trip')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('destination.name')
                    ->label('Destination')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50),
            ]);
    }
}

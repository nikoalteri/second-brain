<?php

namespace App\Filament\Resources\Destination\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DestinationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
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
                TextColumn::make('trip.title')
                    ->label('Trip')
                    ->searchable()
                    ->sortable(),
            ]);
    }
}

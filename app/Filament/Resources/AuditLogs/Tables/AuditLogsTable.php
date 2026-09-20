<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Owner')
                    ->searchable(),
                TextColumn::make('actor.email')
                    ->label('Changed by')
                    ->placeholder('System')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('model_name')
                    ->label('Record')
                    ->searchable(),
                TextColumn::make('model_id')
                    ->label('ID')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('changes')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : $state)
                    ->limit(80)
                    ->tooltip(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null),
                TextColumn::make('ip_address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

<?php

namespace App\Filament\Resources\Notifications\Tables;

use App\Models\Notification;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class NotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Owner')
                    ->visible(fn () => (bool) auth()->user()?->hasRole('superadmin')),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'critical' => 'danger',
                        'exceeded' => 'warning',
                        'warning' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('message')
                    ->wrap()
                    ->limit(120),
                IconColumn::make('is_read')
                    ->label('Read')
                    ->boolean()
                    ->state(fn (Notification $record) => $record->read_at !== null),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('unread')
                    ->label('Unread only')
                    ->queries(
                        true: fn ($query) => $query->whereNull('read_at'),
                        false: fn ($query) => $query->whereNotNull('read_at'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                Action::make('markRead')
                    ->label('Mark read')
                    ->icon('heroicon-o-check')
                    ->visible(fn (Notification $record) => $record->read_at === null && $record->user_id === auth()->id())
                    ->action(fn (Notification $record) => $record->forceFill(['read_at' => now()])->save()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markRead')
                        ->label('Mark read')
                        ->icon('heroicon-o-check')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            // Only the signed-in user's own notifications are ever changed.
                            $records
                                ->where('user_id', auth()->id())
                                ->whereNull('read_at')
                                ->each(fn (Notification $record) => $record->forceFill(['read_at' => now()])->save());
                        }),
                ]),
            ]);
    }
}

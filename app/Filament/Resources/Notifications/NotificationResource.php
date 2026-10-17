<?php

namespace App\Filament\Resources\Notifications;

use App\Filament\Resources\Notifications\Pages\ListNotifications;
use App\Filament\Resources\Notifications\Tables\NotificationsTable;
use App\Models\Notification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    public static function table(Table $table): Table
    {
        return NotificationsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = Notification::query()
            ->withoutUserScope()
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotifications::route('/'),
        ];
    }
}

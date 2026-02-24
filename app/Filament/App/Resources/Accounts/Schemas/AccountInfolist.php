<?php

namespace App\Filament\App\Resources\Accounts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Schemas\Schema;

class AccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                IconEntry::make('icon')
                    ->label('')
                    ->color('info'),

                TextEntry::make('name')
                    ->weight('medium')
                    ->size('xl'),

                TextEntry::make('type')
                    ->badge()
                    ->color(fn(string $state): string =>
                    match ($state) {
                        'bank' => 'primary',
                        'cash' => 'warning',
                        'investment' => 'success',
                        'debt' => 'danger',
                        default => 'gray',
                    }),

                TextEntry::make('signed_balance')
                    ->money('EUR', locale: 'it')
                    ->color(
                        fn(string|int|float $state): string =>
                        (float) $state >= 0 ? 'success' : 'danger'
                    )
                    ->weight('medium'),

                IconEntry::make('is_active')
                    ->color(
                        fn(bool $state): string =>
                        $state ? 'success' : 'danger'
                    )
                    ->boolean(),

                TextEntry::make('user.name')
                    ->badge(),

                TextEntry::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->badge()
                    ->color('gray'),
            ]);
    }
}

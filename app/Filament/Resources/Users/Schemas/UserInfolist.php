<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Name'),

                TextEntry::make('email')
                    ->label('Email'),

                TextEntry::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('No roles'),

                IconEntry::make('is_active')
                    ->label('Active account')
                    ->boolean(),

                TextEntry::make('email_verified_at')
                    ->label('Email verified at')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Not verified'),

                TextEntry::make('created_at')
                    ->label('Created at')
                    ->dateTime('d/m/Y H:i'),

                TextEntry::make('updated_at')
                    ->label('Updated at')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }
}

<?php

namespace App\Filament\Resources\TransactionCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TransactionCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('parent.name')
                    ->label('Parent')
                    ->placeholder('-'),
                TextEntry::make('name'),
                TextEntry::make('color')
                    ->placeholder('-'),
                TextEntry::make('icon')
                    ->placeholder('-'),
                TextEntry::make('budget_monthly')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

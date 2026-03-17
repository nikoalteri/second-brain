<?php

namespace App\Filament\Resources\Loans\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LoanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('account.name')
                    ->label('Account'),
                TextEntry::make('name'),
                TextEntry::make('total_amount')
                    ->numeric(),
                TextEntry::make('monthly_payment')
                    ->numeric(),
                TextEntry::make('withdrawal_day')
                    ->numeric(),
                IconEntry::make('skip_weekends')
                    ->boolean(),
                TextEntry::make('start_date')
                    ->date(),
                TextEntry::make('end_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('total_installments')
                    ->numeric(),
                TextEntry::make('paid_installments')
                    ->numeric(),
                TextEntry::make('remaining_amount')
                    ->numeric(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

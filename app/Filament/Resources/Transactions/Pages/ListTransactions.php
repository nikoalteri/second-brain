<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('finance_report')
                ->label('Finance Report')
                ->icon('heroicon-o-document-chart-bar')
                ->url(fn() => route('filament.admin.pages.finance-report'))
                ->openUrlInNewTab()
                ->color('info'),
        ];
    }
}

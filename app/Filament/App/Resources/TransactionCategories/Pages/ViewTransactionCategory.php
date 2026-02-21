<?php

namespace App\Filament\App\Resources\TransactionCategories\Pages;

use App\Filament\App\Resources\TransactionCategories\TransactionCategoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTransactionCategory extends ViewRecord
{
    protected static string $resource = TransactionCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

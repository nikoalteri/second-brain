<?php

namespace App\Filament\App\Resources\TransactionCategories\Pages;

use App\Filament\App\Resources\TransactionCategories\TransactionCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTransactionCategory extends EditRecord
{
    protected static string $resource = TransactionCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

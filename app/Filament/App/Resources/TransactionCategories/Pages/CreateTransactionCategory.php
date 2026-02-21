<?php

namespace App\Filament\App\Resources\TransactionCategories\Pages;

use App\Filament\App\Resources\TransactionCategories\TransactionCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransactionCategory extends CreateRecord
{
    protected static string $resource = TransactionCategoryResource::class;
}

<?php

namespace App\Filament\Resources\TransactionCategories\Pages;

use App\Filament\Resources\TransactionCategories\TransactionCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransactionCategory extends CreateRecord
{
    protected static string $resource = TransactionCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validazione avanzata tramite FormRequest
        $request = app(\App\Http\Requests\StoreTransactionCategoryRequest::class);
        $request->merge($data);
        $validated = app('validator')->make(
            $request->all(),
            (new \App\Http\Requests\StoreTransactionCategoryRequest())->rules()
        )->validate();
        $data = array_merge($data, $validated);
        return $data;
    }

    protected function authorizeAccess(): void
    {
        $this->authorize('create', static::$resource::getModel());
    }
}

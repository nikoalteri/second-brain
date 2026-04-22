<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        $record = $this->getRecord();

        if (auth()->user()?->can('update', $record)) {
            return UserResource::getUrl('edit', ['record' => $record]);
        }

        return UserResource::getUrl();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['password_confirmation']);
        return $data;
    }
}

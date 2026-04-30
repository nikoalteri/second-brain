<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\PhoneNumber;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

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
        $data['phone'] = PhoneNumber::combine(
            $data['phone_country_code'] ?? null,
            $data['phone_number'] ?? null,
        );

        unset($data['phone_country_code'], $data['phone_number']);
        unset($data['password_confirmation']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $roles = array_values(array_filter((array) $this->data['roles'] ?? []));

        if ($roles !== []) {
            return;
        }

        Role::findOrCreate('user');
        $this->record->assignRole('user');
    }
}

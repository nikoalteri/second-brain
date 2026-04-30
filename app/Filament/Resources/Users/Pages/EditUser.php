<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\PhoneNumber;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): ?string
    {
        return null; // Rimane nella pagina di edit
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $phone = PhoneNumber::split($data['phone'] ?? null);

        $data['phone_country_code'] = $phone['country_code'];
        $data['phone_number'] = $phone['local_number'];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['phone'] = PhoneNumber::combine(
            $data['phone_country_code'] ?? null,
            $data['phone_number'] ?? null,
        );

        unset($data['phone_country_code'], $data['phone_number']);
        unset($data['password_confirmation']);

        return $data;
    }
}

<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract permissions from all checkbox lists
        $permissions = [];
        foreach ($data as $key => $value) {
            if ($key === 'permissions' && is_array($value)) {
                $permissions = array_merge($permissions, $value);
            }
        }

        // Store collected permissions back
        if (!empty($permissions)) {
            $data['permissions'] = $permissions;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Sync permissions to the role
        if (isset($this->data['permissions'])) {
            $this->record->syncPermissions($this->data['permissions']);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}

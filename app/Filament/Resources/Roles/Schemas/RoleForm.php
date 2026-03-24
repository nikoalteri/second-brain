<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Services\PermissionService;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextInputField;
use Filament\Schemas\Components\CheckboxListField;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $permissionService = app(PermissionService::class);
        $groupedPermissions = $permissionService->getGroupedPermissions();

        $components = [
            Section::make('Role Information')
                ->components([
                    TextInputField::make('name')
                        ->required()
                        ->maxLength(255),
                ]),
        ];

        // Add checkbox section for each permission group
        foreach ($groupedPermissions as $groupName => $permissions) {
            $components[] = Section::make($groupName . ' Permissions')
                ->collapsed(false)
                ->components([
                    CheckboxListField::make('permissions')
                        ->relationship('permissions', 'name')
                        ->options($permissions)
                        ->columns(2),
                ]);
        }

        return $schema->components($components);
    }
}

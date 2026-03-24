<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Services\PermissionService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
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
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                ]),
        ];

        // Add checkbox section for each permission group
        foreach ($groupedPermissions as $groupName => $permissions) {
            $components[] = Section::make($groupName . ' Permissions')
                ->collapsed(false)
                ->components([
                    CheckboxList::make('permissions')
                        ->options($permissions)
                        ->columns(2),
                ]);
        }

        return $schema->components($components);
    }
}

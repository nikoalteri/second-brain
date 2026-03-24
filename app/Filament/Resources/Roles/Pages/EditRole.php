<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Services\PermissionService;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    public function form(Form $form): Form
    {
        $permissionService = app(PermissionService::class);
        $groupedPermissions = $permissionService->getGroupedPermissions();

        $sections = [
            Section::make('Role Information')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique('roles', 'name', ignoreRecord: true),
                ]),
        ];

        // Add checkbox section for each permission group
        foreach ($groupedPermissions as $groupName => $permissions) {
            $sections[] = Section::make($groupName . ' Permissions')
                ->collapsible()
                ->collapsed(false)
                ->schema([
                    CheckboxList::make('permissions')
                        ->relationship('permissions', 'name')
                        ->options($permissions)
                        ->columns(2),
                ]);
        }

        return $form->schema($sections);
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

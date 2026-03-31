<?php

namespace App\Filament\Resources\UserSettings;

use App\Filament\Resources\UserSettings\Pages\CreateUserSetting;
use App\Filament\Resources\UserSettings\Pages\EditUserSetting;
use App\Filament\Resources\UserSettings\Pages\ListUserSettings;
use App\Filament\Resources\UserSettings\Schemas\UserSettingForm;
use App\Filament\Resources\UserSettings\Tables\UserSettingsTable;
use App\Models\UserSetting;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserSettingResource extends Resource
{
    protected static ?string $model = UserSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return UserSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserSettings::route('/'),
            'create' => CreateUserSetting::route('/create'),
            'edit' => EditUserSetting::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

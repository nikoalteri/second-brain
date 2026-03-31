<?php

namespace App\Filament\Resources\BloodTests;

use App\Filament\Resources\BloodTests\Pages\CreateBloodTest;
use App\Filament\Resources\BloodTests\Pages\EditBloodTest;
use App\Filament\Resources\BloodTests\Pages\ListBloodTests;
use App\Filament\Resources\BloodTests\Schemas\BloodTestForm;
use App\Filament\Resources\BloodTests\Tables\BloodTestsTable;
use App\Models\BloodTest;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BloodTestResource extends Resource
{
    protected static ?string $model = BloodTest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    public static function form(Schema $schema): Schema
    {
        return BloodTestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BloodTestsTable::configure($table);
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
            'index' => ListBloodTests::route('/'),
            'create' => CreateBloodTest::route('/create'),
            'edit' => EditBloodTest::route('/{record}/edit'),
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

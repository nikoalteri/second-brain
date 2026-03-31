<?php

namespace App\Filament\Resources\HealthRecords;

use App\Filament\Resources\HealthRecords\Pages\CreateHealthRecord;
use App\Filament\Resources\HealthRecords\Pages\EditHealthRecord;
use App\Filament\Resources\HealthRecords\Pages\ListHealthRecords;
use App\Filament\Resources\HealthRecords\Schemas\HealthRecordForm;
use App\Filament\Resources\HealthRecords\Tables\HealthRecordsTable;
use App\Models\HealthRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HealthRecordResource extends Resource
{
    protected static ?string $model = HealthRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'date';

    public static function form(Schema $schema): Schema
    {
        return HealthRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HealthRecordsTable::configure($table);
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
            'index' => ListHealthRecords::route('/'),
            'create' => CreateHealthRecord::route('/create'),
            'edit' => EditHealthRecord::route('/{record}/edit'),
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

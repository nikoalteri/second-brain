<?php

namespace App\Filament\Resources\Habits;

use App\Filament\Resources\Habits\Pages\CreateHabit;
use App\Filament\Resources\Habits\Pages\EditHabit;
use App\Filament\Resources\Habits\Pages\ListHabits;
use App\Filament\Resources\Habits\Schemas\HabitForm;
use App\Filament\Resources\Habits\Tables\HabitsTable;
use App\Models\Habit;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HabitResource extends Resource
{
    protected static ?string $model = Habit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Productivity';

    public static function form(Schema $schema): Schema
    {
        return HabitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HabitsTable::configure($table);
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
            'index' => ListHabits::route('/'),
            'create' => CreateHabit::route('/create'),
            'edit' => EditHabit::route('/{record}/edit'),
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

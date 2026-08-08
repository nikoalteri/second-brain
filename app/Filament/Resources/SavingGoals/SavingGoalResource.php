<?php

namespace App\Filament\Resources\SavingGoals;

use App\Filament\Resources\SavingGoals\Pages\CreateSavingGoal;
use App\Filament\Resources\SavingGoals\Pages\EditSavingGoal;
use App\Filament\Resources\SavingGoals\Pages\ListSavingGoals;
use App\Filament\Resources\SavingGoals\RelationManagers\ContributionsRelationManager;
use App\Filament\Resources\SavingGoals\Schemas\SavingGoalForm;
use App\Filament\Resources\SavingGoals\Tables\SavingGoalsTable;
use App\Models\SavingGoal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SavingGoalResource extends Resource
{
    protected static ?string $model = SavingGoal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;
    protected static string|UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Saving Goals';
    protected static ?string $singularLabel = 'Saving Goal';
    protected static ?int $navigationOrder = 4;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SavingGoalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SavingGoalsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::user()?->hasRole('superadmin')) {
            return $query;
        }

        return $query->where('user_id', Auth::id());
    }

    public static function getRelations(): array
    {
        return [
            ContributionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSavingGoals::route('/'),
            'create' => CreateSavingGoal::route('/create'),
            'edit' => EditSavingGoal::route('/{record}/edit'),
        ];
    }
}

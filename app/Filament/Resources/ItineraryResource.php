<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Itinerary\Pages\CreateItinerary;
use App\Filament\Resources\Itinerary\Pages\EditItinerary;
use App\Filament\Resources\Itinerary\Pages\ListItineraries;
use App\Filament\Resources\Itinerary\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Itinerary\Schemas\ItineraryForm;
use App\Filament\Resources\Itinerary\Tables\ItinerariesTable;
use App\Models\Itinerary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ItineraryResource extends Resource
{
    protected static ?string $model = Itinerary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;
    protected static string|UnitEnum|null $navigationGroup = 'Travel';
    protected static ?string $navigationLabel = 'Itineraries';
    protected static ?string $singularLabel = 'Itinerary';
    protected static ?int $navigationOrder = 3;
    protected static ?string $recordTitleAttribute = 'date';

    public static function form(Schema $schema): Schema
    {
        return ItineraryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItinerariesTable::configure($table)
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItineraries::route('/'),
            'create' => CreateItinerary::route('/create'),
            'edit' => EditItinerary::route('/{record}/edit'),
        ];
    }
}

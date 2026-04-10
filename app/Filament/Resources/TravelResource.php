<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Travel\Pages\CreateTrip;
use App\Filament\Resources\Travel\Pages\EditTrip;
use App\Filament\Resources\Travel\Pages\ListTrips;
use App\Filament\Resources\Travel\RelationManagers\DestinationsRelationManager;
use App\Filament\Resources\Travel\RelationManagers\ItinerariesRelationManager;
use App\Filament\Resources\Travel\RelationManagers\ParticipantsRelationManager;
use App\Filament\Resources\Travel\Schemas\TripForm;
use App\Filament\Resources\Travel\Tables\TripsTable;
use App\Models\Trip;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;
use App\Enums\TripStatus;

class TravelResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Travel';
    protected static ?string $navigationLabel = 'Trips';
    protected static ?string $singularLabel = 'Trip';
    protected static ?int $navigationOrder = 1;
    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return TripForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TripsTable::configure($table)
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(TripStatus::class),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
            ])
            ->recordActions([
                \Filament\Tables\Actions\Action::make('export-pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(function (Trip $record): mixed {
                        return app(\App\Services\TravelPdfExporter::class)->export($record);
                    }),
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
            DestinationsRelationManager::class,
            ItinerariesRelationManager::class,
            ParticipantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrips::route('/'),
            'create' => CreateTrip::route('/create'),
            'edit' => EditTrip::route('/{record}/edit'),
        ];
    }
}

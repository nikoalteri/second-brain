<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Destination\Pages\CreateDestination;
use App\Filament\Resources\Destination\Pages\EditDestination;
use App\Filament\Resources\Destination\Schemas\DestinationForm;
use App\Filament\Resources\Destination\Tables\DestinationsTable;
use App\Models\Destination;
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

class DestinationResource extends Resource
{
    protected static ?string $model = Destination::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;
    protected static string|UnitEnum|null $navigationGroup = 'Travel';
    protected static ?string $navigationLabel = 'Destinations';
    protected static ?string $singularLabel = 'Destination';
    protected static ?int $navigationOrder = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DestinationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DestinationsTable::configure($table)
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

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Destination\Pages\ListDestinations::route('/'),
            'create' => CreateDestination::route('/create'),
            'edit' => EditDestination::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Properties\Pages\CreateProperty;
use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Resources\Properties\Pages\ListProperties;
use App\Filament\Resources\Properties\RelationManagers\MaintenanceTasksRelationManager;
use App\Filament\Resources\Properties\RelationManagers\UtilitiesRelationManager;
use App\Filament\Resources\Properties\RelationManagers\InventoriesRelationManager;
use App\Models\Property;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use UnitEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static string|UnitEnum|null $navigationGroup = 'Home Management';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Tabs::make('Property Details')->tabs([
                Tabs\Tab::make('Basic Info')->schema([
                    TextInput::make('address')
                        ->required()
                        ->maxLength(255)
                        ->label('Street Address'),
                    Select::make('property_type')
                        ->options([
                            'house' => 'House',
                            'apartment' => 'Apartment',
                            'condo' => 'Condo',
                            'commercial' => 'Commercial',
                        ])
                        ->required(),
                    DatePicker::make('lease_start_date')
                        ->label('Lease Start Date'),
                    DatePicker::make('lease_end_date')
                        ->label('Lease End Date'),
                    TextInput::make('estimated_value')
                        ->numeric()
                        ->prefix('$')
                        ->step(0.01)
                        ->label('Estimated Value'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('address')
                ->searchable()
                ->sortable(),
            TextColumn::make('property_type')
                ->badge(),
            TextColumn::make('lease_start_date')
                ->date('M d, Y'),
            TextColumn::make('estimated_value')
                ->money('USD'),
            TextColumn::make('maintenanceTasks.count')
                ->label('Tasks')
                ->counts('maintenanceTasks'),
            TextColumn::make('utilities.count')
                ->label('Utilities')
                ->counts('utilities'),
            TextColumn::make('inventories.count')
                ->label('Items')
                ->counts('inventories'),
        ])->filters([
            SelectFilter::make('property_type')
                ->options([
                    'house' => 'House',
                    'apartment' => 'Apartment',
                    'condo' => 'Condo',
                    'commercial' => 'Commercial',
                ]),
            Filter::make('has_lease')
                ->query(fn(Builder $query) => $query->whereNotNull('lease_start_date')),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            MaintenanceTasksRelationManager::class,
            UtilitiesRelationManager::class,
            InventoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'),
            'create' => CreateProperty::route('/create'),
            'edit' => EditProperty::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::user()?->hasRole('superadmin')) {
            return $query;
        }

        return $query->where('user_id', Auth::id());
    }
}

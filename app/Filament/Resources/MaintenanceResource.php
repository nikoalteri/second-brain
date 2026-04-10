<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Maintenance\Pages\CreateMaintenance;
use App\Filament\Resources\Maintenance\Pages\EditMaintenance;
use App\Filament\Resources\Maintenance\Pages\ListMaintenances;
use App\Filament\Resources\Maintenance\RelationManagers\RecordsRelationManager;
use App\Models\MaintenanceTask;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use UnitEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MaintenanceResource extends Resource
{
    protected static ?string $model = MaintenanceTask::class;
    protected static string|UnitEnum|null $navigationGroup = 'Home Management';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('property_id')
                ->relationship('property', 'address')
                ->required(),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->options([
                    'preventive' => 'Preventive',
                    'repair' => 'Repair',
                    'inspection' => 'Inspection',
                    'upgrade' => 'Upgrade',
                ])
                ->required(),
            Select::make('frequency')
                ->options([
                    'weekly' => 'Weekly',
                    'monthly' => 'Monthly',
                    'quarterly' => 'Quarterly',
                    'annually' => 'Annually',
                    'as_needed' => 'As Needed',
                ])
                ->required(),
            Textarea::make('description'),
            DatePicker::make('last_completed_date'),
            DatePicker::make('next_due_date')
                ->disabled()
                ->hint('Auto-calculated based on frequency'),
            Select::make('status')
                ->options([
                    'active' => 'Active',
                    'paused' => 'Paused',
                    'completed' => 'Completed',
                ])
                ->default('active')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('property.address')
                ->searchable()
                ->limit(30),
            TextColumn::make('name')
                ->searchable(),
            TextColumn::make('type')
                ->badge(),
            TextColumn::make('frequency')
                ->badge(),
            TextColumn::make('status')
                ->badge(),
            TextColumn::make('next_due_date')
                ->date('M d, Y'),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RecordsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenances::route('/'),
            'create' => CreateMaintenance::route('/create'),
            'edit' => EditMaintenance::route('/{record}/edit'),
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

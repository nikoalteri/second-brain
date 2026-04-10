<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Utilities\Pages\CreateUtility;
use App\Filament\Resources\Utilities\Pages\EditUtility;
use App\Filament\Resources\Utilities\Pages\ListUtilities;
use App\Filament\Resources\Utilities\RelationManagers\BillsRelationManager;
use App\Models\Utility;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use UnitEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UtilityResource extends Resource
{
    protected static ?string $model = Utility::class;
    protected static string|UnitEnum|null $navigationGroup = 'Home Management';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('property_id')
                ->relationship('property', 'address')
                ->required(),
            Select::make('type')
                ->options([
                    'electricity' => 'Electricity',
                    'gas' => 'Gas',
                    'water' => 'Water',
                    'internet' => 'Internet',
                    'phone' => 'Phone',
                    'waste' => 'Waste',
                ])
                ->required(),
            TextInput::make('provider')
                ->required(),
            TextInput::make('account_number'),
            Select::make('billing_cycle')
                ->options([
                    'monthly' => 'Monthly',
                    'quarterly' => 'Quarterly',
                    'annual' => 'Annual',
                ])
                ->required(),
            TextInput::make('billing_day')
                ->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('property.address')
                ->label('Property')
                ->searchable()
                ->limit(25),
            TextColumn::make('type')
                ->badge(),
            TextColumn::make('provider'),
            TextColumn::make('billing_cycle'),
            TextColumn::make('utilityBills.count')
                ->label('Bills')
                ->counts('utilityBills'),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            BillsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUtilities::route('/'),
            'create' => CreateUtility::route('/create'),
            'edit' => EditUtility::route('/{record}/edit'),
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

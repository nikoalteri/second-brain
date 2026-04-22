<?php

namespace App\Filament\Resources\SubscriptionFrequencies;

use App\Filament\Resources\SubscriptionFrequencies\Pages\CreateSubscriptionFrequency;
use App\Filament\Resources\SubscriptionFrequencies\Pages\EditSubscriptionFrequency;
use App\Filament\Resources\SubscriptionFrequencies\Pages\ListSubscriptionFrequencies;
use App\Filament\Resources\SubscriptionFrequencies\Schemas\SubscriptionFrequencyForm;
use App\Filament\Resources\SubscriptionFrequencies\Tables\SubscriptionFrequenciesTable;
use App\Models\SubscriptionFrequency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SubscriptionFrequencyResource extends Resource
{
    protected static ?string $model = SubscriptionFrequency::class;
    protected static string|UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Subscription Frequencies';
    protected static ?string $singularLabel = 'Subscription Frequency';
    protected static ?int $navigationOrder = 4;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SubscriptionFrequencyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionFrequenciesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionFrequencies::route('/'),
            'create' => CreateSubscriptionFrequency::route('/create'),
            'edit' => EditSubscriptionFrequency::route('/{record}/edit'),
        ];
    }
}

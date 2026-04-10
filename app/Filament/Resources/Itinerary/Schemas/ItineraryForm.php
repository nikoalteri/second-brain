<?php

namespace App\Filament\Resources\Itinerary\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ItineraryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Itinerary Details')
                    ->components([
                        Select::make('trip_id')
                            ->label('Trip')
                            ->relationship('trip', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('date')
                            ->label('Date')
                            ->required(),
                        Select::make('destination_id')
                            ->label('Destination')
                            ->relationship(
                                name: 'destination',
                                titleAttribute: 'name',
                            )
                            ->searchable()
                            ->preload(),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }
}

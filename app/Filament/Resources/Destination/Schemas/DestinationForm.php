<?php

namespace App\Filament\Resources\Destination\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DestinationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Destination Details')
                    ->components([
                        TextInput::make('name')
                            ->label('Destination Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Paris, France'),
                        TextInput::make('country')
                            ->label('Country')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('timezone')
                            ->label('Timezone')
                            ->default('UTC')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->step(0.000001)
                            ->placeholder('48.856613'),
                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->step(0.000001)
                            ->placeholder('2.352222'),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }
}

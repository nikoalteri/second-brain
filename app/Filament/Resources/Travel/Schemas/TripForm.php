<?php

namespace App\Filament\Resources\Travel\Schemas;

use App\Enums\TripStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Trip Details')
                    ->components([
                        TextInput::make('title')
                            ->label('Trip Title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Summer Europe 2026'),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->placeholder('Trip overview and notes...'),
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(TripStatus::class)
                            ->default(TripStatus::PLANNED->value)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}

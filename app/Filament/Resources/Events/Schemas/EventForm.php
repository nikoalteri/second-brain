<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                DateTimePicker::make('event_date')
                    ->required(),
                Select::make('event_type')
                    ->options([
            'meeting' => 'Meeting',
            'birthday' => 'Birthday',
            'anniversary' => 'Anniversary',
            'other' => 'Other',
        ])
                    ->default('other')
                    ->required(),
                TextInput::make('location'),
                TextInput::make('attendees_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}

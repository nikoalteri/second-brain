<?php

namespace App\Filament\Resources\JournalEntries\Schemas;

use App\Enums\JournalMood;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class JournalEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title'),
                DatePicker::make('date')
                    ->required(),
                Select::make('mood')
                    ->options(JournalMood::class),
                Textarea::make('content')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}

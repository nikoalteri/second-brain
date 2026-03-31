<?php

namespace App\Filament\Resources\Notes\Schemas;

use App\Enums\NotePriority;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class NoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Select::make('priority')
                    ->options(NotePriority::class)
                    ->default(NotePriority::MEDIUM),
                DatePicker::make('date'),
                Textarea::make('content')
                    ->required()
                    ->columnSpanFull(),
                Checkbox::make('is_pinned')
                    ->label('Pin this note'),
            ]);
    }
}

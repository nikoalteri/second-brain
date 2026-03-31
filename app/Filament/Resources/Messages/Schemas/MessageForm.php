<?php

namespace App\Filament\Resources\Messages\Schemas;

use App\Enums\MessageImportance;
use App\Enums\MessageCategory;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('to_user_id')
                    ->numeric(),
                TextInput::make('subject')
                    ->required(),
                Textarea::make('content')
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('read_at'),
                Select::make('importance')
                    ->options(MessageImportance::class)
                    ->default(MessageImportance::MEDIUM)
                    ->required(),
                Select::make('category')
                    ->options(MessageCategory::class)
                    ->default(MessageCategory::PERSONAL)
                    ->required(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Contacts\Schemas;

use App\Enums\ContactRelationshipType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                Select::make('relationship_type')
                    ->options(ContactRelationshipType::class)
                    ->default(ContactRelationshipType::FRIEND)
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                DatePicker::make('birthday'),
            ]);
    }
}

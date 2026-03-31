<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DocumentForm
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
                Select::make('document_type')
                    ->options([
            'title' => 'Title',
            'insurance' => 'Insurance',
            'registration' => 'Registration',
            'maintenance' => 'Maintenance',
            'other' => 'Other',
        ])
                    ->default('other')
                    ->required(),
                TextInput::make('upload_path')
                    ->required(),
                DatePicker::make('upload_date')
                    ->required(),
            ]);
    }
}

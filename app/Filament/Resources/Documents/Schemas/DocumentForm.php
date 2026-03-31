<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Select::make('type')
                    ->options(DocumentType::class)
                    ->required(),
                DatePicker::make('date_created')
                    ->required(),
                FileUpload::make('file_path')
                    ->disk('documents')
                    ->directory('documents'),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}

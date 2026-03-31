<?php

namespace App\Filament\Resources\HealthRecords\Schemas;

use App\Enums\HealthRecordType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class HealthRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(HealthRecordType::class)
                    ->required(),
                DatePicker::make('date')
                    ->required(),
                TextInput::make('value')
                    ->numeric()
                    ->step(0.01)
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

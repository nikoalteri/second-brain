<?php

namespace App\Filament\Resources\BloodTests\Schemas;

use App\Enums\BloodTestResultStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BloodTestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('test_name')
                    ->required(),
                DatePicker::make('test_date')
                    ->required(),
                TextInput::make('result_value')
                    ->numeric()
                    ->step(0.01)
                    ->required(),
                TextInput::make('normal_range')
                    ->required(),
                Select::make('result_status')
                    ->options(BloodTestResultStatus::class)
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}

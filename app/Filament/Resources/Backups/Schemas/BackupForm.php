<?php

namespace App\Filament\Resources\Backups\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BackupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Select::make('backup_type')
                    ->options(['auto' => 'Auto', 'manual' => 'Manual'])
                    ->default('auto')
                    ->required(),
                DateTimePicker::make('backup_date')
                    ->required(),
                TextInput::make('file_path')
                    ->required(),
                TextInput::make('file_size')
                    ->numeric(),
            ]);
    }
}

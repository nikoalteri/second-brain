<?php

namespace App\Filament\Resources\TransactionCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Schema;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Builder;

class TransactionCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                ColorPicker::make('color')
                    ->default('#3B82F6'),

                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}

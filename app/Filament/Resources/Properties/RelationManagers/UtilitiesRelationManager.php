<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UtilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'utilities';

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('type')
                ->options([
                    'electricity' => 'Electricity',
                    'gas' => 'Gas',
                    'water' => 'Water',
                    'internet' => 'Internet',
                    'phone' => 'Phone',
                    'waste' => 'Waste',
                ])
                ->required(),
            TextInput::make('provider')
                ->required(),
            TextInput::make('account_number'),
            Select::make('billing_cycle')
                ->options([
                    'monthly' => 'Monthly',
                    'quarterly' => 'Quarterly',
                    'annual' => 'Annual',
                ])
                ->required(),
            TextInput::make('billing_day')
                ->numeric(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('type')
                ->badge(),
            TextColumn::make('provider'),
            TextColumn::make('account_number'),
            TextColumn::make('billing_cycle')
                ->badge(),
        ])->filters([
            //
        ])->headerActions([
            CreateAction::make(),
        ])->actions([
            EditAction::make(),
            DeleteAction::make(),
        ]);
    }
}

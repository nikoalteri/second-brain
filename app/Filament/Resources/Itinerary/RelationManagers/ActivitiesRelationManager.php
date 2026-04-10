<?php

namespace App\Filament\Resources\Itinerary\RelationManagers;

use App\Enums\ActivityType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Activities';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Activity Title')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Type')
                    ->options(ActivityType::class)
                    ->required(),
                DateTimePicker::make('start_time')
                    ->label('Start Time')
                    ->required(),
                DateTimePicker::make('end_time')
                    ->label('End Time')
                    ->required(),
                TextInput::make('cost')
                    ->label('Cost')
                    ->numeric()
                    ->prefix('€')
                    ->step(0.01)
                    ->minValue(0),
                Select::make('currency')
                    ->label('Currency')
                    ->options([
                        'EUR' => 'EUR',
                        'USD' => 'USD',
                        'GBP' => 'GBP',
                        'JPY' => 'JPY',
                    ])
                    ->default('EUR'),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label('Activity')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Start Time')
                    ->dateTime('M d, H:i')
                    ->sortable(),
                TextColumn::make('cost')
                    ->label('Cost')
                    ->money()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}

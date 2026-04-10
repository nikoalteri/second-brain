<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceTasks';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->options([
                    'preventive' => 'Preventive',
                    'repair' => 'Repair',
                    'inspection' => 'Inspection',
                    'upgrade' => 'Upgrade',
                ])
                ->required(),
            Select::make('frequency')
                ->options([
                    'weekly' => 'Weekly',
                    'monthly' => 'Monthly',
                    'quarterly' => 'Quarterly',
                    'annually' => 'Annually',
                    'as_needed' => 'As Needed',
                ])
                ->required(),
            Textarea::make('description'),
            DatePicker::make('last_completed_date')
                ->label('Last Completed'),
            Select::make('status')
                ->options([
                    'active' => 'Active',
                    'paused' => 'Paused',
                    'completed' => 'Completed',
                ])
                ->default('active')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')
                ->searchable(),
            TextColumn::make('type')
                ->badge(),
            TextColumn::make('frequency')
                ->badge(),
            TextColumn::make('status')
                ->badge(),
            TextColumn::make('next_due_date')
                ->date('M d, Y'),
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

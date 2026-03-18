<?php

namespace App\Filament\Resources\Loans\RelationManagers;

use App\Enums\LoanPaymentStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Services\LoanScheduleService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Installments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('due_date')
                    ->label('Due Date')
                    ->required(),

                DatePicker::make('actual_date')
                    ->label('Payment Date'),

                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('€')
                    ->required(),

                Select::make('status')
                    ->label('Status')
                    ->options(LoanPaymentStatus::class)
                    ->default(LoanPaymentStatus::PENDING->value)
                    ->required(),

                TextInput::make('interest_rate')
                    ->label('Interest Rate (%)')
                    ->numeric()
                    ->step(0.01)
                    ->suffix('%')
                    ->nullable()
                    ->visible(fn() => $this->getOwnerRecord()->is_variable_rate),

                TextInput::make('notes')
                    ->label('Notes')
                    ->maxLength(255),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('actual_date')
                    ->label('Paid On')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('interest_rate')
                    ->label('Rate')
                    ->formatStateUsing(fn($state) => $state !== null ? number_format((float) $state, 2) . '%' : '-')
                    ->toggleable()
                    ->visible(fn() => $this->getOwnerRecord()->is_variable_rate),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('recalculatePayments')
                    ->label('Recalculate future payments')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('new_rate')
                            ->label('New Interest Rate (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01),
                        Placeholder::make('info')
                            ->content(fn() => 'Pending installments: '
                                . $this->getOwnerRecord()->payments()->where('status', 'pending')->count()
                                . ' — Remaining capital: €'
                                . number_format(
                                    max(0, (float) $this->getOwnerRecord()->total_amount - (float) $this->getOwnerRecord()->payments()->where('status', 'paid')->sum('amount')),
                                    2,
                                    ',',
                                    '.'
                                )),
                    ])
                    ->action(function (array $data) {
                        $loan = $this->getOwnerRecord();
                        app(LoanScheduleService::class)->recalculateFuturePayments($loan, (float) $data['new_rate']);

                        Notification::make()
                            ->title('Future payments recalculated')
                            ->success()
                            ->send();
                    })
                    ->visible(fn() => $this->getOwnerRecord()->is_variable_rate),
                Action::make('generateSchedule')
                    ->label('Generate schedule')
                    ->icon('heroicon-o-calendar-days')
                    ->requiresConfirmation()
                    ->action(function () {
                        $loan = $this->getOwnerRecord();

                        app(LoanScheduleService::class)->generate($loan, onlyMissing: true);

                        Notification::make()
                            ->title('Payment schedule generated')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('due_date');
    }
}

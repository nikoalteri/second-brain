<?php

namespace App\Filament\Resources\CreditCards\RelationManagers;

use App\Enums\CreditCardCycleStatus;
use App\Services\CreditCardCycleService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Carbon\Carbon;

class CyclesRelationManager extends RelationManager
{
    protected static string $relationship = 'cycles';

    protected static ?string $title = 'Statement cycles';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('period_start_date')
                    ->label('Period start')
                    ->helperText('Select the cycle start date.')
                    ->rules(['before_or_equal:statement_date'])
                    ->required()
                    ->native(false),
                DatePicker::make('statement_date')
                    ->label('Statement date')
                    ->helperText('Date when statement is issued.')
                    ->rules(['after_or_equal:period_start_date'])
                    ->required()
                    ->native(false),
                DatePicker::make('due_date')
                    ->label('Due date')
                    ->helperText('Payment deadline.')
                    ->native(false),
                TextInput::make('total_spent')
                    ->label('Total spent')
                    ->numeric()
                    ->prefix('EUR')
                    ->default(0)
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options(CreditCardCycleStatus::class)
                    ->default(CreditCardCycleStatus::OPEN->value)
                    ->required(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('statement_date', 'desc')
            ->columns([
                TextColumn::make('period_month')
                    ->label('Period')
                    ->sortable(),
                TextColumn::make('period_range')
                    ->label('Range')
                    ->getStateUsing(function ($record): string {
                        $start = $record->period_start_date
                            ? Carbon::parse($record->period_start_date)
                            : Carbon::parse($record->period_month . '-01');
                        $end = Carbon::parse($record->statement_date);

                        return $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y');
                    }),
                TextColumn::make('statement_date')
                    ->label('Statement')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Due')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('total_spent')
                    ->label('Spent')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('interest_amount')
                    ->label('Interest')
                    ->money('EUR'),
                TextColumn::make('stamp_duty_amount')
                    ->label('Stamp')
                    ->money('EUR'),
                TextColumn::make('total_due')
                    ->label('Total due')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn(array $data): array => $this->normalizeCycleData($data)),
            ])
            ->recordActions([
                Action::make('issueCycle')
                    ->label('Issue cycle')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $issued = app(CreditCardCycleService::class)->issueCycle($record);

                        if (! $issued) {
                            Notification::make()
                                ->title('Unable to issue cycle: set a fixed installment and ensure it covers interest')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Cycle issued')
                            ->success()
                            ->send();
                    })
                    ->visible(fn($record) => $record->status === CreditCardCycleStatus::OPEN),
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        $periodStart = ! empty($data['period_start_date'])
                            ? Carbon::parse($data['period_start_date'])
                            : Carbon::parse($data['period_month'] . '-01');

                        $data['period_start_date'] = $periodStart->toDateString();

                        return $data;
                    })
                    ->mutateDataUsing(fn(array $data): array => $this->normalizeCycleData($data)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    private function normalizeCycleData(array $data): array
    {
        $periodStart = Carbon::parse($data['period_start_date']);
        $statementDate = Carbon::parse($data['statement_date']);

        $data['period_start_date'] = $periodStart->toDateString();
        $data['period_month'] = $periodStart->format('Y-m');
        $data['statement_date'] = $statementDate->toDateString();

        if (empty($data['due_date'])) {
            $dueDate = $statementDate->copy()->day(19);

            if ($dueDate->lessThanOrEqualTo($statementDate)) {
                $dueDate = $dueDate->addMonthNoOverflow()->day(19);
            }

            $data['due_date'] = $dueDate->toDateString();
        }

        return $data;
    }
}

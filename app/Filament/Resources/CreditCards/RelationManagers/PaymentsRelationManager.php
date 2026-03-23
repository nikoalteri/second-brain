<?php

namespace App\Filament\Resources\CreditCards\RelationManagers;

use App\Enums\CreditCardPaymentStatus;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('due_date')
                    ->label('Due')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('actual_date')
                    ->label('Paid on')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->getStateUsing(fn($record) => $record->total_amount ?? (
                        (float) ($record->principal_amount ?? 0)
                        + (float) ($record->interest_amount ?? 0)
                        + (float) ($record->stamp_duty_amount ?? 0)
                    ))
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('principal_amount')
                    ->label('Principal')
                    ->money('EUR')
                    ->toggleable(),
                TextColumn::make('interest_amount')
                    ->label('Interest')
                    ->money('EUR')
                    ->toggleable(),
                TextColumn::make('stamp_duty_amount')
                    ->label('Stamp')
                    ->money('EUR')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->recordActions([
                Action::make('markAsPaid')
                    ->label('Mark as paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => CreditCardPaymentStatus::PAID,
                            'actual_date' => $record->actual_date ?? now()->toDateString(),
                        ]);

                        Notification::make()
                            ->title('Payment marked as paid')
                            ->success()
                            ->send();
                    })
                    ->visible(fn($record) => $record->status === CreditCardPaymentStatus::PENDING),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}

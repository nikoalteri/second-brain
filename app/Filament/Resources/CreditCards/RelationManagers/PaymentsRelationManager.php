<?php

namespace App\Filament\Resources\CreditCards\RelationManagers;

use App\Enums\CreditCardPaymentStatus;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
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
                    ->money(fn () => Money::currency(), locale: fn () => Money::locale())
                    ->sortable(),
                TextColumn::make('principal_amount')
                    ->label('Principal')
                    ->money(fn () => Money::currency(), locale: fn () => Money::locale())
                    ->toggleable(),
                TextColumn::make('interest_amount')
                    ->label('Interest charged')
                    ->money(fn () => Money::currency(), locale: fn () => Money::locale())
                    ->toggleable(),
                IconColumn::make('confirmed_interest_amount')
                    ->label('Confirmed')
                    ->boolean()
                    ->state(fn ($record) => $record->confirmed_interest_amount !== null)
                    ->tooltip(fn ($record) => $record->confirmed_interest_amount !== null
                        ? 'Interest matches the real statement'
                        : 'Interest is estimated, not yet confirmed against a statement'),
                TextColumn::make('stamp_duty_amount')
                    ->label('Stamp duty')
                    ->money(fn () => Money::currency(), locale: fn () => Money::locale())
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->recordActions([
                Action::make('confirmRealInterest')
                    ->label('Confirm real interest')
                    ->icon('heroicon-o-document-check')
                    ->color('gray')
                    ->form([
                        TextInput::make('confirmed_interest_amount')
                            ->label('Interest from the real statement')
                            ->numeric()
                            ->prefix('EUR')
                            ->required(),
                    ])
                    ->fillForm(fn ($record) => [
                        'confirmed_interest_amount' => $record->confirmed_interest_amount ?? $record->interest_amount,
                    ])
                    ->action(function ($record, array $data) {
                        $statementInterest = round((float) $data['confirmed_interest_amount'], 2);
                        $stampDuty = (float) $record->stamp_duty_amount;
                        $totalAmount = (float) $record->total_amount;
                        $newPrincipal = round(max(0.0, $totalAmount - $statementInterest - $stampDuty), 2);

                        $record->update([
                            'confirmed_interest_amount' => $statementInterest,
                            'interest_amount' => $statementInterest,
                            'principal_amount' => $newPrincipal,
                        ]);

                        if ($record->cycle) {
                            $record->cycle->update([
                                'interest_amount' => $statementInterest,
                                'principal_amount' => $newPrincipal,
                            ]);
                        }

                        Notification::make()
                            ->title('Interest confirmed, balance recalculated')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->status === CreditCardPaymentStatus::PAID),
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

<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountsResource;
use App\Models\Account;
use App\Services\AccountTransferService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('transfer')
                ->label('Transfer money')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->form([
                    Select::make('from_account_id')
                        ->label('From')
                        ->options(fn () => Account::query()->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Select::make('to_account_id')
                        ->label('To')
                        ->options(fn () => Account::query()->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->different('from_account_id'),
                    TextInput::make('amount')
                        ->numeric()
                        ->prefix('EUR')
                        ->minValue(0.01)
                        ->required(),
                    DatePicker::make('date')
                        ->native(false)
                        ->default(now())
                        ->required(),
                    Textarea::make('description')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $from = Account::query()->findOrFail($data['from_account_id']);
                    $to = Account::query()->findOrFail($data['to_account_id']);

                    try {
                        app(AccountTransferService::class)->transfer(
                            $from,
                            $to,
                            (float) $data['amount'],
                            $data['date'],
                            $data['description'] ?: null,
                        );
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Transfer failed')
                            ->body(collect($e->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Transfer completed')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}

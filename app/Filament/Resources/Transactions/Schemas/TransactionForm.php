<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Models\Account;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('account_id')
                    ->label('Account')
                    ->options(function () {
                        $query = Account::where('is_active', true);
                        if (! auth()->user()?->hasRole('superadmin')) {
                            $query->where('user_id', auth()->id());
                        }
                        return $query->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required(),

                Select::make('transaction_type_id')  // ✅ PRIMA
                    ->label('Type')
                    ->relationship('type', 'name')
                    ->required()
                    ->live(),

                Select::make('to_account_id')        // ✅ DOPO
                    ->label('Destination account')
                    ->options(function () {
                        $query = Account::where('is_active', true);
                        if (! auth()->user()?->hasRole('superadmin')) {
                            $query->where('user_id', auth()->id());
                        }
                        return $query->pluck('name', 'id');
                    })
                    ->visible(
                        fn(Get $get) =>
                        TransactionType::find($get('transaction_type_id'))?->name === 'Transfer'
                    )
                    ->required(
                        fn(Get $get) =>
                        TransactionType::find($get('transaction_type_id'))?->name === 'Transfer'
                    ),
                Select::make('transaction_category_id')
                    ->label('Category')
                    ->options(function () {
                        return TransactionCategory::whereNotNull('parent_id')
                            ->where(function ($query) {
                                $query->whereNull('user_id')
                                    ->orWhere('user_id', auth()->id());
                            })
                            ->with('parent')
                            ->get()
                            ->mapWithKeys(fn($cat) => [
                                $cat->id => $cat->parent->name . ' › ' . $cat->name
                            ]);
                    })
                    ->searchable()
                    ->nullable(),


                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->prefix('€')
                    ->minValue(0.01)
                    ->required(),

                DatePicker::make('date')
                    ->label('Date')
                    ->default(now())
                    ->required(),

                TextInput::make('description')
                    ->label('Description')
                    ->placeholder('e.g. McDonald\'s'),
                Textarea::make('notes')
                    ->label('Notes')
                    ->columnSpanFull(),
            ]);
    }
}

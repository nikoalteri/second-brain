<?php

namespace App\Filament\Resources\Loans\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\LoanStatus;

class LoanForm
{
    private static function calcMonthlyPayment(float $total, float $rate, int $n): ?float
    {
        if ($n <= 0 || $total <= 0) {
            return null;
        }

        if ($rate > 0) {
            $r = ($rate / 100) / 12;
            return round(($total * $r * (1 + $r) ** $n) / ((1 + $r) ** $n - 1), 2);
        }

        return round($total / $n, 2);
    }

    private static function calcOutstandingPrincipal(float $total, float $rate, float $monthly, int $k): float
    {
        if ($total <= 0 || $monthly <= 0) {
            return $total;
        }

        if ($rate > 0 && $k > 0) {
            $r = ($rate / 100) / 12;
            return max(0.0, round(
                $total * (1 + $r) ** $k - $monthly * ((1 + $r) ** $k - 1) / $r,
                2
            ));
        }

        return max(0.0, round($total - $k * $monthly, 2));
    }

    private static function recalcAll(callable $set, callable $get): void
    {
        $total = (float) ($get('total_amount') ?? 0);
        $isVar = (bool) ($get('is_variable_rate') ?? false);
        $rate  = $isVar ? 0.0 : (float) ($get('interest_rate') ?? 0);
        $n     = (int) ($get('total_installments') ?? 0);
        $k     = (int) ($get('paid_installments') ?? 0);

        $mp = self::calcMonthlyPayment($total, $rate, $n);
        if ($mp === null) {
            return;
        }

        $set('monthly_payment', $mp);
        $set('remaining_amount', self::calcOutstandingPrincipal($total, $rate, $mp, $k));
    }

    public static function computeMonthlyPayment(array $data): float
    {
        $total = (float) ($data['total_amount'] ?? 0);
        $isVar = (bool) ($data['is_variable_rate'] ?? false);
        $rate  = $isVar ? 0.0 : (float) ($data['interest_rate'] ?? 0);
        $n     = (int) ($data['total_installments'] ?? 0);
        return self::calcMonthlyPayment($total, $rate, $n) ?? 0.0;
    }

    public static function computeRemainingAmount(array $data): float
    {
        $total = (float) ($data['total_amount'] ?? 0);
        $isVar = (bool) ($data['is_variable_rate'] ?? false);
        $rate  = $isVar ? 0.0 : (float) ($data['interest_rate'] ?? 0);
        $n     = (int) ($data['total_installments'] ?? 0);
        $k     = (int) ($data['paid_installments'] ?? 0);
        $mp    = self::calcMonthlyPayment($total, $rate, $n) ?? 0.0;
        return self::calcOutstandingPrincipal($total, $rate, $mp, $k);
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Main data')
                    ->components([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Select::make('account_id')
                            ->label('Account')
                            ->relationship(
                                name: 'account',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->when(
                                        ! auth()->user()?->hasRole('superadmin'),
                                        fn(Builder $query) => $query->where('user_id', auth()->id())
                                    )
                                    ->where('is_active', true)
                            )
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->prefix('€')
                            ->step(0.01)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::recalcAll($set, $get)),
                        TextInput::make('interest_rate')
                            ->label('Interest Rate (%)')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->nullable()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::recalcAll($set, $get))
                            ->visible(fn(callable $get) => ! $get('is_variable_rate')),
                        Toggle::make('is_variable_rate')
                            ->label('Variable Rate')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if ($state) {
                                    $set('interest_rate', null);
                                }
                                self::recalcAll($set, $get);
                            })
                            ->inline(false),
                        TextInput::make('total_installments')
                            ->label('Total Installments')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::recalcAll($set, $get)),
                        TextInput::make('monthly_payment')
                            ->label('Monthly Payment')
                            ->prefix('€')
                            ->numeric()
                            ->readOnly()
                            ->helperText('Calculated: (Total Amount + Interest) / Total Installments')
                            ->placeholder('Auto-calculated'),
                        TextInput::make('remaining_amount')
                            ->label('Remaining Amount')
                            ->prefix('€')
                            ->numeric()
                            ->readOnly()
                            ->helperText('Outstanding principal balance after paid installments')
                            ->placeholder('Auto-calculated'),
                        Placeholder::make('total_interest_value')
                            ->label('Total Interest (Full Loan)')
                            ->content(function (callable $get): string {
                                $total = (float) ($get('total_amount') ?? 0);
                                $isVar = (bool) ($get('is_variable_rate') ?? false);
                                $rate  = $isVar ? 0.0 : (float) ($get('interest_rate') ?? 0);
                                $n     = (int) ($get('total_installments') ?? 0);
                                $mp    = self::calcMonthlyPayment($total, $rate, $n);
                                if ($mp === null || $total <= 0) return '€ 0,00';
                                return '€ ' . number_format(max(0, ($mp * $n) - $total), 2, ',', '.');
                            }),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Schedule')
                    ->components([
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('End Date'),
                        TextInput::make('withdrawal_day')
                            ->label('Withdrawal Day')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->required(),
                        Toggle::make('skip_weekends')
                            ->label('Skip Weekends')
                            ->default(true)
                            ->inline(false),
                        TextInput::make('paid_installments')
                            ->label('Paid Installments')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, callable $set, callable $get) => self::recalcAll($set, $get)),
                        Select::make('status')
                            ->options(LoanStatus::class)
                            ->default(LoanStatus::ACTIVE->value)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}

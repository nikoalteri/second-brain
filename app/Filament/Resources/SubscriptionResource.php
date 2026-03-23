<?php

namespace App\Filament\Resources;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\DatePickerField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\SelectField;
use Filament\Schemas\Components\TextInputField;
use Filament\Schemas\Components\TextareaField;
use Filament\Schemas\Components\ToggleField;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Subscription';
    protected static ?string $singularLabel = 'Subscription';
    protected static ?int $navigationOrder = 5;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Subscription Info')
                    ->schema([
                        TextInputField::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Netflix, Spotify'),

                        SelectField::make('frequency')
                            ->options(SubscriptionFrequency::class)
                            ->required()
                            ->live(),
                    ])
                    ->columns(2),

                Section::make('Cost')
                    ->schema([
                        TextInputField::make('monthly_cost')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->helperText('For monthly subscriptions'),

                        TextInputField::make('annual_cost')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->helperText('For annual/biennial subscriptions'),
                    ])
                    ->columns(2),

                Section::make('Renewal')
                    ->schema([
                        TextInputField::make('day_of_month')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->default(1)
                            ->helperText('Day of month for renewal'),

                        DatePickerField::make('next_renewal_date')
                            ->required()
                            ->helperText('Next scheduled renewal'),
                    ])
                    ->columns(2),

                Section::make('Account & Category')
                    ->schema([
                        SelectField::make('account_id')
                            ->relationship('account', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Account to debit'),

                        SelectField::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Transaction category (optional)'),
                    ])
                    ->columns(2),

                Section::make('Settings')
                    ->schema([
                        SelectField::make('status')
                            ->options(SubscriptionStatus::class)
                            ->required()
                            ->default(SubscriptionStatus::ACTIVE),

                        ToggleField::make('auto_create_transaction')
                            ->default(false)
                            ->helperText('Auto-create transaction on renewal'),
                    ])
                    ->columns(2),

                Section::make('Notes')
                    ->schema([
                        TextareaField::make('notes')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('frequency')
                    ->sortable(),

                TextColumn::make('monthly_cost')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('next_renewal_date')
                    ->date()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SubscriptionStatus::class),

                SelectFilter::make('frequency')
                    ->options(SubscriptionFrequency::class),
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
                \Filament\Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    \Filament\Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\SubscriptionResource\Pages\ListSubscriptions::route('/'),
            'create' => \App\Filament\Resources\SubscriptionResource\Pages\CreateSubscription::route('/create'),
            'edit' => \App\Filament\Resources\SubscriptionResource\Pages\EditSubscription::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::user()?->hasRole('superadmin')) {
            return $query;
        }

        return $query->where('user_id', Auth::id());
    }
}

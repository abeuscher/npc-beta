<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DonationResource\Pages;
use App\Filament\Resources\DonationResource\RelationManagers;
use App\Models\Donation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DonationResource extends Resource
{
    protected static ?string $model = Donation::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_donation') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Select::make('contact_id')
                    ->label('Contact')
                    ->relationship('contact', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->disabled(),

                Forms\Components\TextInput::make('type')->disabled(),

                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->prefix('$')
                    ->disabled(),

                Forms\Components\TextInput::make('frequency')->disabled(),

                Forms\Components\TextInput::make('status')->disabled(),

                Forms\Components\TextInput::make('currency')->disabled(),

                Forms\Components\TextInput::make('stripe_subscription_id')
                    ->label('Stripe Subscription ID')
                    ->disabled(),

                Forms\Components\TextInput::make('stripe_customer_id')
                    ->label('Stripe Customer ID')
                    ->disabled(),

                Forms\Components\DateTimePicker::make('started_at')->disabled(),

                Forms\Components\DateTimePicker::make('ended_at')->disabled(),

                Forms\Components\TextInput::make('fund.name')
                    ->label('Fund')
                    ->disabled()
                    ->placeholder('—'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contact.display_name')
                    ->label('Contact')
                    ->searchable(query: fn ($query, $search) => $query->whereHas('contact', fn ($q) => $q
                        ->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                    ))
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'one_off',
                        'success' => 'recurring',
                    ]),

                Tables\Columns\TextColumn::make('amount')->money('USD')->sortable(),

                Tables\Columns\TextColumn::make('frequency')->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'active',
                        'danger'  => 'past_due',
                        'gray'    => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('fund.name')
                    ->label('Fund')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('stripe')
                    ->label('View in Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn ($record) => $record->stripe_subscription_id
                        ? 'https://dashboard.stripe.com/subscriptions/' . $record->stripe_subscription_id
                        : null
                    )
                    ->openUrlInNewTab()
                    ->hidden(fn ($record) => ! $record->stripe_subscription_id),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDonations::route('/'),
            'view'  => Pages\ViewDonation::route('/{record}'),
        ];
    }
}

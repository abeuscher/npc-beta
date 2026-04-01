<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Jobs\SyncTransactionToQuickBooks;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Transaction;
use App\Services\QuickBooks\QuickBooksAuth;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_transaction') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->stripe_id;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return ! $record->stripe_id;
    }

    private const MANUAL_TYPES = [
        'grant'      => 'Grant',
        'adjustment' => 'Adjustment',
    ];

    private const TYPE_DIRECTION_MAP = [
        'grant'      => 'in',
        'adjustment' => 'in',
    ];

    public static function form(Form $form): Form
    {
        $isEdit = $form->getOperation() === 'edit';

        return $form->schema([
            Forms\Components\Section::make('Off-system transaction')
                ->description('Use this form to record inbound transactions that occurred outside any connected system — for example, a grant received by cheque or a manual fund adjustment. Payments, refunds, and any other transactions processed through Stripe are recorded automatically and cannot be entered here.')
                ->schema([
                    // Known manual type — normal select
                    Forms\Components\Select::make('type')
                        ->options(self::MANUAL_TYPES)
                        ->default('grant')
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('direction', self::TYPE_DIRECTION_MAP[$state] ?? 'in'))
                        ->visible(fn (?Transaction $record): bool => ! $record || array_key_exists($record->type, self::MANUAL_TYPES)),

                    // Unknown type (Stripe-originated record opened defensively) — disabled text
                    Forms\Components\TextInput::make('type')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?Transaction $record): bool => $record && ! array_key_exists($record->type, self::MANUAL_TYPES)),

                    Forms\Components\Hidden::make('direction')
                        ->default('in'),

                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->prefix('$')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'cleared' => 'Cleared',
                        ])
                        ->default('pending')
                        ->required(),

                    Forms\Components\Select::make('contact_id')
                        ->label('Contact')
                        ->relationship('contact', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                        ->searchable(['first_name', 'last_name', 'email'])
                        ->nullable()
                        ->columnSpan(2),

                    Forms\Components\DateTimePicker::make('occurred_at')
                        ->default(now())
                        ->required()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('quickbooks_id')
                        ->label('QuickBooks reference')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?Transaction $record): bool => $record && filled($record->quickbooks_id))
                        ->columnSpan(2),
                ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        $qbConnected = app(QuickBooksAuth::class)->isConnected();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contact.display_name')
                    ->label('Contact')
                    ->placeholder('—')
                    ->searchable(query: fn ($query, $search) => $query->whereHas('contact', fn ($q) => $q
                        ->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                    )),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->state(fn (Transaction $record): string => match ($record->subject_type) {
                        Donation::class          => 'Donation',
                        Purchase::class          => 'Purchase',
                        EventRegistration::class => 'Event',
                        Membership::class        => 'Membership',
                        default                  => $record->stripe_id ? 'Stripe' : 'Manual',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Donation'   => 'success',
                        'Purchase'   => 'info',
                        'Event'      => 'warning',
                        'Membership' => 'primary',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subject_label')
                    ->label('Description')
                    ->state(function (Transaction $record): string {
                        $subject = $record->subject;
                        return match ($record->subject_type) {
                            Donation::class          => $subject?->fund?->name ?? 'General donation',
                            Purchase::class          => $subject?->product?->name ?? 'Product purchase',
                            EventRegistration::class => $subject?->event?->title ?? 'Event registration',
                            Membership::class        => $subject?->tier?->name ? $subject->tier->name . ' membership' : 'Membership',
                            default                  => ucfirst($record->type),
                        };
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('amount')->money('USD')->sortable(),

                Tables\Columns\TextColumn::make('direction')->badge()
                    ->color(fn ($state) => $state === 'in' ? 'success' : 'danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn ($state) => match ($state) {
                        'cleared', 'completed' => 'success',
                        'failed'               => 'danger',
                        'refunded'             => 'warning',
                        default                => 'gray',
                    }),

                Tables\Columns\TextColumn::make('qb_status')
                    ->label('QuickBooks')
                    ->badge()
                    ->state(function (Transaction $record) use ($qbConnected): string {
                        if (! $qbConnected) {
                            return 'N/A';
                        }
                        if (filled($record->quickbooks_id)) {
                            return 'Synced';
                        }
                        if (filled($record->qb_sync_error)) {
                            return 'Error';
                        }
                        return 'Pending';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Synced'  => 'success',
                        'Error'   => 'danger',
                        'Pending' => 'warning',
                        default   => 'gray',
                    })
                    ->tooltip(function (Transaction $record): ?string {
                        if (filled($record->qb_synced_at)) {
                            $tip = 'Synced ' . $record->qb_synced_at->format('M j, Y g:i A');
                            $qbCustId = $record->contact?->quickbooks_customer_id;
                            if (filled($qbCustId)) {
                                $tip .= " — QB Customer #{$qbCustId}";
                            }
                            return $tip;
                        }
                        if (filled($record->qb_sync_error)) {
                            return mb_substr($record->qb_sync_error, 0, 200);
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('occurred_at')->dateTime()->sortable(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with([
                'subject',
                'contact',
            ]))
            ->defaultSort('occurred_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('contact_id')
                    ->label('Contact')
                    ->options(fn () => Contact::orderByRaw("COALESCE(last_name, first_name)")
                        ->get()
                        ->mapWithKeys(fn ($c) => [$c->id => $c->display_name]))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Product')
                    ->options(fn () => Product::orderBy('name')->pluck('name', 'id'))
                    ->query(fn ($query, array $data) => isset($data['value']) && $data['value']
                        ? $query
                            ->where('subject_type', Purchase::class)
                            ->whereIn('subject_id', Purchase::where('product_id', $data['value'])->pluck('id'))
                        : $query
                    ),

                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Category')
                    ->options([
                        Donation::class          => 'Donation',
                        Purchase::class          => 'Purchase',
                        EventRegistration::class => 'Event registration',
                        Membership::class        => 'Membership',
                    ])
                    ->placeholder('All categories'),
            ])
            ->actions([
                Tables\Actions\Action::make('stripe')
                    ->label('Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Transaction $record): ?string => $record->stripe_id
                        ? match (true) {
                            str_starts_with($record->stripe_id, 'in_') => 'https://dashboard.stripe.com/invoices/' . $record->stripe_id,
                            str_starts_with($record->stripe_id, 'cs_') => 'https://dashboard.stripe.com/checkout/sessions/' . $record->stripe_id,
                            str_starts_with($record->stripe_id, 're_') => 'https://dashboard.stripe.com/refunds/' . $record->stripe_id,
                            default                                     => 'https://dashboard.stripe.com/payments/' . $record->stripe_id,
                        }
                        : null
                    )
                    ->openUrlInNewTab()
                    ->hidden(fn (Transaction $record): bool => ! $record->stripe_id),

                Tables\Actions\Action::make('qb_sync')
                    ->label('Sync to QB')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Re-sync to QuickBooks')
                    ->modalDescription('This will attempt to sync this transaction to QuickBooks.')
                    ->action(function (Transaction $record): void {
                        SyncTransactionToQuickBooks::dispatch($record);
                        Notification::make()->title('Sync job dispatched')->success()->send();
                    })
                    ->hidden(fn (Transaction $record): bool =>
                        filled($record->quickbooks_id)
                        || ! $qbConnected
                        || ! auth()->user()?->can('manage_financial_settings')
                    ),

                Tables\Actions\EditAction::make()
                    ->visible(fn (Transaction $record): bool => ! $record->stripe_id),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Transaction $record): bool => ! $record->stripe_id),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit'   => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}

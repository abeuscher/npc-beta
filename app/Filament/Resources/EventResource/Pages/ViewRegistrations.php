<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\TransactionResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Transaction;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ViewRegistrations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.pages.event-registrations';

    public Event $record;

    public function mount(Event|int|string $record): void
    {
        $this->record = $record instanceof Event ? $record : Event::findOrFail($record);
    }

    public function getTitle(): string
    {
        return 'Registrations — ' . $this->record->title;
    }

    public function getBreadcrumbs(): array
    {
        return [
            EventResource::getUrl('index') => 'Events',
            EventResource::getUrl('edit', ['record' => $this->record]) => $this->record->title,
            'Registrations',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                EventRegistration::query()->where('event_id', $this->record->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('company')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'pending',
                        'success' => 'registered',
                        'warning' => 'waitlisted',
                        'danger'  => 'cancelled',
                        'info'    => 'attended',
                    ]),

                Tables\Columns\TextColumn::make('paid')
                    ->label('Paid')
                    ->badge()
                    ->state(fn (EventRegistration $record): string =>
                        $record->stripe_payment_intent_id ? 'Yes' : 'No'
                    )
                    ->color(fn (string $state): string =>
                        $state === 'Yes' ? 'success' : 'gray'
                    ),

                Tables\Columns\TextColumn::make('registered_at')
                    ->label('Registered')
                    ->date('M j, Y')
                    ->sortable(),
            ])
            ->defaultSort('registered_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('view_contact')
                    ->label('View Contact Record')
                    ->icon('heroicon-o-user')
                    ->color('gray')
                    ->url(fn (EventRegistration $record): ?string =>
                        $record->contact_id
                            ? ContactResource::getUrl('edit', ['record' => $record->contact_id])
                            : null
                    )
                    ->hidden(fn (EventRegistration $record): bool => ! $record->contact_id),

                Tables\Actions\Action::make('view_stripe')
                    ->label('Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (EventRegistration $record): ?string =>
                        $record->stripe_payment_intent_id
                            ? 'https://dashboard.stripe.com/payments/' . $record->stripe_payment_intent_id
                            : null
                    )
                    ->openUrlInNewTab()
                    ->hidden(fn (EventRegistration $record): bool => ! $record->stripe_payment_intent_id),

                Tables\Actions\Action::make('view_transaction')
                    ->label('Transaction')
                    ->icon('heroicon-o-receipt-percent')
                    ->color('gray')
                    ->url(function (EventRegistration $record): ?string {
                        $transaction = Transaction::where('subject_type', EventRegistration::class)
                            ->where('subject_id', $record->id)
                            ->first();

                        return $transaction
                            ? TransactionResource::getUrl('edit', ['record' => $transaction])
                            : null;
                    })
                    ->hidden(function (EventRegistration $record): bool {
                        return ! Transaction::where('subject_type', EventRegistration::class)
                            ->where('subject_id', $record->id)
                            ->exists();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->modalHeading(fn (EventRegistration $record): string =>
                        $record->stripe_payment_intent_id
                            ? 'This is a paid registration'
                            : 'Delete registration'
                    )
                    ->modalDescription(fn (EventRegistration $record): ?string =>
                        $record->stripe_payment_intent_id
                            ? 'This registrant paid via Stripe. Please issue the refund in your Stripe dashboard before deleting this registration. Are you sure you want to proceed?'
                            : null
                    ),
            ])
            ->emptyStateHeading('No registrations')
            ->emptyStateDescription('No one has registered for this event yet.');
    }
}

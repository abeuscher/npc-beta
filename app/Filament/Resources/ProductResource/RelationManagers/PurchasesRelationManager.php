<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'purchases';

    protected static ?string $title = 'Purchases';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('contact.display_name')
                    ->label('Contact')
                    ->searchable(['contacts.first_name', 'contacts.last_name', 'contacts.email'])
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('price.label')
                    ->label('Price Tier'),

                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount')
                    ->money('USD'),

                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:ia')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'danger'  => 'cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('stripe')
                    ->label('View in Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn ($record) => $record->stripe_session_id
                        ? 'https://dashboard.stripe.com/' . (str_starts_with($record->stripe_session_id, 'cs_test_') ? 'test/' : '') . 'checkout/sessions/' . $record->stripe_session_id
                        : null
                    )
                    ->openUrlInNewTab()
                    ->hidden(fn ($record) => ! $record->stripe_session_id),
            ]);
    }
}

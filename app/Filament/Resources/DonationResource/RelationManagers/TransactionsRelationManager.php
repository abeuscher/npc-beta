<?php

namespace App\Filament\Resources\DonationResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

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
                Tables\Columns\TextColumn::make('stripe_id')
                    ->label('Stripe ID')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'completed',
                        'danger'  => 'failed',
                    ]),

                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:ia')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('stripe')
                    ->label('View in Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn ($record) => $record->stripe_id
                        ? (str_starts_with($record->stripe_id, 'in_')
                            ? 'https://dashboard.stripe.com/invoices/' . $record->stripe_id
                            : 'https://dashboard.stripe.com/checkout/sessions/' . $record->stripe_id)
                        : null
                    )
                    ->openUrlInNewTab()
                    ->hidden(fn ($record) => ! $record->stripe_id),
            ]);
    }
}

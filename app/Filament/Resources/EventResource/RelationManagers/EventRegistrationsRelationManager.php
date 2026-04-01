<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventRegistration;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventRegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'Registrations';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        $event = $this->getOwnerRecord();

        if ($event->registrants_deleted_at) {
            return $table
                ->recordTitleAttribute('name')
                ->columns([])
                ->actions([])
                ->emptyStateIcon('heroicon-o-trash')
                ->emptyStateHeading('Event registrants deleted')
                ->emptyStateDescription('All registrant contacts and registration records for this event have been removed.');
        }

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'registered',
                        'warning' => 'waitlisted',
                        'danger'  => 'cancelled',
                        'info'    => 'attended',
                    ]),

                Tables\Columns\TextColumn::make('registered_at')
                    ->label('Registered')
                    ->date('M j, Y')
                    ->sortable(),
            ])
            ->actions([
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
            ->defaultSort('registered_at', 'desc');
    }
}

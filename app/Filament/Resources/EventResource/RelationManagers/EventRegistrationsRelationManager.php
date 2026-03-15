<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

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
        return true;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('eventDate.starts_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

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
            ->actions([])
            ->defaultSort('registered_at', 'desc');
    }
}

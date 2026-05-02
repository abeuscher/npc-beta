<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use App\Filament\Resources\EventResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventsSponsoredRelationManager extends RelationManager
{
    protected static string $relationship = 'eventsSponsored';

    protected static ?string $title = 'Events Sponsored';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('starts_at')->label('Date')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->recordUrl(fn ($record) => EventResource::getUrl('edit', ['record' => $record]))
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

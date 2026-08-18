<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Route;

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
            // Resolved by route name — the resource lives in the Events plugin,
            // which core cannot reference. Rows lose their link (not their data)
            // when the plugin is absent.
            ->recordUrl(fn ($record) => Route::has('filament.admin.resources.events.edit')
                ? route('filament.admin.resources.events.edit', ['record' => $record])
                : null)
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

<?php

namespace App\Filament\Resources\OrganizationResource\RelationManagers;

use App\Filament\Resources\ContactResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AffiliatedContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliations';

    protected static ?string $title = 'Affiliated Contacts';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('contact.display_name')
                    ->label('Contact')
                    ->searchable(['contact.first_name', 'contact.last_name']),

                Tables\Columns\TextColumn::make('role')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
            ])
            ->defaultSort('is_primary', 'desc')
            ->recordUrl(fn ($record) => ContactResource::getUrl('edit', ['record' => $record->contact_id]))
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

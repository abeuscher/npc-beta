<?php

namespace App\Filament\Resources\FormResource\RelationManagers;

use App\Filament\Resources\ContactResource;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FormSubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $title = 'Submissions';

    public function isReadOnly(): bool
    {
        return false; // we allow delete
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Submission data')->schema([
                Infolists\Components\KeyValueEntry::make('data')
                    ->label('')
                    ->columnSpanFull(),
            ]),
            Infolists\Components\Section::make('Meta')->schema([
                Infolists\Components\TextEntry::make('ip_address')->label('IP address'),
                Infolists\Components\TextEntry::make('created_at')->label('Submitted at')->dateTime(),
            ])->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP address'),

                Tables\Columns\IconColumn::make('contact_id')
                    ->label('Contact created')
                    ->boolean()
                    ->getStateUsing(fn ($record) => (bool) $record->contact_id),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View contact')
                    ->icon('heroicon-o-user')
                    ->url(fn ($record) => $record->contact_id
                        ? ContactResource::getUrl('edit', ['record' => $record->contact_id])
                        : null
                    )
                    ->visible(fn ($record) => (bool) $record->contact_id),

                Tables\Actions\ViewAction::make()
                    ->label('View data')
                    ->visible(fn ($record) => ! $record->contact_id),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->can('delete_form_submission')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->can('delete_form_submission')),
                ]),
            ]);
    }
}

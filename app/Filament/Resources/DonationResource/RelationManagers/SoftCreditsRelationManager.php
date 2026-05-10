<?php

namespace App\Filament\Resources\DonationResource\RelationManagers;

use App\Models\Contact;
use App\Models\DonationCredit;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SoftCreditsRelationManager extends RelationManager
{
    protected static string $relationship = 'softCredits';

    protected static ?string $title = 'Soft credits';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            MorphToSelect::make('attributable')
                ->label('Recipient')
                ->types([
                    MorphToSelect\Type::make(Contact::class)
                        ->label('Contact')
                        ->titleAttribute('first_name')
                        ->searchColumns(['first_name', 'last_name', 'email'])
                        ->getOptionLabelFromRecordUsing(fn (Contact $record) => $record->display_name),
                    MorphToSelect\Type::make(Organization::class)
                        ->label('Organization')
                        ->titleAttribute('name'),
                ])
                ->searchable()
                ->required(),

            Forms\Components\TextInput::make('credit_pct')
                ->label('Credit %')
                ->numeric()
                ->default(100)
                ->required()
                ->suffix('%')
                ->minValue(0),

            Forms\Components\TextInput::make('credit_role')
                ->label('Role')
                ->placeholder('e.g. Honour of, In memory of, Triggered by, Match recipient')
                ->datalist(fn () => DonationCredit::query()
                    ->whereNotNull('credit_role')
                    ->distinct()
                    ->pluck('credit_role')
                    ->all()),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('attributable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),

                Tables\Columns\TextColumn::make('recipient')
                    ->label('Recipient')
                    ->getStateUsing(function (DonationCredit $record): string {
                        $target = $record->attributable;

                        if ($target instanceof Contact) {
                            return $target->display_name;
                        }

                        if ($target instanceof Organization) {
                            return $target->name;
                        }

                        return '—';
                    }),

                Tables\Columns\TextColumn::make('credit_pct')
                    ->label('Credit %')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 2), '0'), '.') . '%'),

                Tables\Columns\TextColumn::make('credit_role')
                    ->label('Role')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

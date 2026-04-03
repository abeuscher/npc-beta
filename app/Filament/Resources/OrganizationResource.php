<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Filament\Resources\OrganizationResource\RelationManagers\ContactsRelationManager;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('type')
                    ->options([
                        'foundation'  => 'Foundation',
                        'corporate'   => 'Corporate',
                        'government'  => 'Government',
                        'other'       => 'Other',
                    ])
                    ->nullable(),

                Forms\Components\TextInput::make('website')
                    ->url()
                    ->maxLength(255),

                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(50),
            ])->columns(2),

            Forms\Components\Section::make('Address')
                ->schema([
                    Forms\Components\TextInput::make('address_line_1')
                        ->label('Address Line 1')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('address_line_2')
                        ->label('Address Line 2')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('city'),
                    Forms\Components\TextInput::make('state'),
                    Forms\Components\TextInput::make('postal_code'),
                    Forms\Components\TextInput::make('country')->default('US'),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Notes')->schema([
                Forms\Components\Textarea::make('notes')
                    ->rows(4)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'foundation' => 'info',
                        'corporate'  => 'warning',
                        'government' => 'success',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('city')
                    ->sortable(),

                Tables\Columns\TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->counts('contacts')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'foundation' => 'Foundation',
                        'corporate'  => 'Corporate',
                        'government' => 'Government',
                        'other'      => 'Other',
                    ]),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
            \App\Filament\Resources\OrganizationResource\RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit'   => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}

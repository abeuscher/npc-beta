<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Affiliation')
                ->schema([
                    Forms\Components\Select::make('organization_id')
                        ->label('Organization')
                        ->relationship('organization', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ]),

            Forms\Components\Section::make('Name')
                ->schema([
                    Forms\Components\TextInput::make('prefix')
                        ->label('Prefix')
                        ->placeholder('Mr, Ms, Dr…'),

                    Forms\Components\TextInput::make('preferred_name')
                        ->label('Preferred Name'),

                    Forms\Components\TextInput::make('first_name')
                        ->label('First Name'),

                    Forms\Components\TextInput::make('last_name')
                        ->label('Last Name'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Contact Information')
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->label('Primary Email'),

                    Forms\Components\TextInput::make('email_secondary')
                        ->email()
                        ->label('Secondary Email'),

                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->label('Primary Phone'),

                    Forms\Components\TextInput::make('phone_secondary')
                        ->tel()
                        ->label('Secondary Phone'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Address')
                ->schema([
                    Forms\Components\TextInput::make('address_line_1')
                        ->label('Address Line 1')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('address_line_2')
                        ->label('Address Line 2')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('city')
                        ->label('City'),

                    Forms\Components\TextInput::make('state')
                        ->label('State'),

                    Forms\Components\TextInput::make('postal_code')
                        ->label('Postal Code'),

                    Forms\Components\TextInput::make('country')
                        ->label('Country')
                        ->default('US'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Flags')
                ->schema([
                    Forms\Components\Toggle::make('is_deceased')
                        ->label('Deceased'),

                    Forms\Components\Toggle::make('do_not_contact')
                        ->label('Do Not Contact'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Additional')
                ->schema([
                    Forms\Components\Select::make('source')
                        ->label('Source')
                        ->options([
                            'manual' => 'Manual Entry',
                            'import' => 'Import',
                            'form'   => 'Web Form',
                            'api'    => 'API',
                        ])
                        ->nullable(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->columns(2),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable(query: function ($query, string $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%");
                        });
                    })
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw(
                        "COALESCE(last_name, first_name) {$direction}"
                    )),

                Tables\Columns\TextColumn::make('roles')
                    ->label('Roles')
                    ->getStateUsing(function (Contact $record): string {
                        $roles = [];
                        if ($record->isMember()) {
                            $roles[] = 'Member';
                        }
                        if ($record->isDonor()) {
                            $roles[] = 'Donor';
                        }

                        return implode(', ', $roles);
                    })
                    ->placeholder('—')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('household.name')
                    ->label('Household')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('city_state')
                    ->label('Location')
                    ->getStateUsing(fn (Contact $record) => collect([$record->city, $record->state])
                        ->filter()
                        ->implode(', ')
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('do_not_contact')
                    ->label('Do Not Contact'),

                Tables\Filters\TernaryFilter::make('is_deceased')
                    ->label('Deceased'),

                Tables\Filters\Filter::make('is_member')
                    ->label('Members only')
                    ->query(fn ($query) => $query->isMember()),

                Tables\Filters\Filter::make('is_donor')
                    ->label('Donors only')
                    ->query(fn ($query) => $query->isDonor()),

                Tables\Filters\Filter::make('in_household')
                    ->label('In a household')
                    ->query(fn ($query) => $query->whereNotNull('household_id')),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with([
                'household',
                'memberships' => fn ($q) => $q->where('status', 'active'),
                'donations',
            ]));
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ContactResource\RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit'   => Pages\EditContact::route('/{record}/edit'),
        ];
    }
}

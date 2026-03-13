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

            Forms\Components\Section::make('Contact Type')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->options([
                            'individual' => 'Individual',
                            'organization' => 'Organization',
                        ])
                        ->default('individual')
                        ->required()
                        ->live(),
                ]),

            Forms\Components\Section::make('Name')
                ->schema([
                    Forms\Components\TextInput::make('organization_name')
                        ->label('Organization Name')
                        ->visible(fn (Forms\Get $get) => $get('type') === 'organization')
                        ->requiredIf('type', 'organization'),

                    Forms\Components\TextInput::make('prefix')
                        ->label('Prefix')
                        ->placeholder('Mr, Ms, Dr…')
                        ->visible(fn (Forms\Get $get) => $get('type') === 'individual'),

                    Forms\Components\TextInput::make('first_name')
                        ->label('First Name')
                        ->visible(fn (Forms\Get $get) => $get('type') === 'individual'),

                    Forms\Components\TextInput::make('last_name')
                        ->label('Last Name')
                        ->visible(fn (Forms\Get $get) => $get('type') === 'individual'),

                    Forms\Components\TextInput::make('preferred_name')
                        ->label('Preferred Name'),
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
                            'form' => 'Web Form',
                            'api' => 'API',
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
                                ->orWhere('last_name', 'ilike', "%{$search}%")
                                ->orWhere('organization_name', 'ilike', "%{$search}%");
                        });
                    })
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw(
                        "COALESCE(organization_name, last_name) {$direction}"
                    )),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'individual',
                        'success' => 'organization',
                    ]),

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
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'individual' => 'Individual',
                        'organization' => 'Organization',
                    ]),

                Tables\Filters\TernaryFilter::make('do_not_contact')
                    ->label('Do Not Contact'),

                Tables\Filters\TernaryFilter::make('is_deceased')
                    ->label('Deceased'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }
}

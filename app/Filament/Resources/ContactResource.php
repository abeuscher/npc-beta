<?php

namespace App\Filament\Resources;

use App\Filament\Pages\ImportContactsPage;
use App\Filament\Resources\ContactResource\Pages;
use App\Forms\Components\TagSelect;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Group::make([

                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('prefix')
                            ->label('Prefix')
                            ->placeholder('Mr, Ms, Dr…')
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->columnSpan(5),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->columnSpan(5),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->label('Email')
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->label('Phone')
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('address_line_1')
                            ->label('Address Line 1')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('address_line_2')
                            ->label('Address Line 2')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->columnSpan(5),

                        Forms\Components\TextInput::make('state')
                            ->label('State')
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('postal_code')
                            ->label('ZIP')
                            ->columnSpan(3),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Custom Fields')
                    ->schema(fn () => CustomFieldDef::forModel('contact')->get()
                        ->map(fn ($def) => $def->toFilamentFormComponent())
                        ->toArray()
                    )
                    ->columns(2)
                    ->hidden(fn () => CustomFieldDef::forModel('contact')->doesntExist()),

                Forms\Components\Section::make('Affiliation')
                    ->schema([
                        Forms\Components\Select::make('organization_id')
                            ->label('Organization')
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ]),

            ])->columnSpan(2),

            Forms\Components\Section::make('Settings')
                ->schema([
                    TagSelect::make('contact'),

                    Forms\Components\Select::make('source')
                        ->label('Source')
                        ->options([
                            'manual' => 'Manual Entry',
                            'import' => 'Import',
                            'form'   => 'Web Form',
                            'api'    => 'API',
                        ])
                        ->nullable(),

                    Forms\Components\Toggle::make('do_not_contact')
                        ->label('Do Not Contact'),

                    Forms\Components\Toggle::make('mailing_list_opt_in')
                        ->label('Mailing List Opt-In'),
                ])
                ->columnSpan(1),

        ])->columns(3);
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
            ->headerActions([
                Tables\Actions\Action::make('importContacts')
                    ->label('Import Contacts')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->url(ImportContactsPage::getUrl()),

                Tables\Actions\Action::make('exportContacts')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (HasTable $livewire): StreamedResponse {
                        $query    = $livewire->getFilteredSortedTableQuery();
                        $filename = 'contacts-' . now()->format('Y-m-d') . '.csv';

                        $customDefs = CustomFieldDef::forModel('contact')->get();

                        return response()->streamDownload(function () use ($query, $customDefs) {
                            $handle = fopen('php://output', 'w');

                            $standardHeaders = [
                                'first_name', 'last_name', 'email', 'phone',
                                'address_line_1', 'address_line_2', 'city', 'state',
                                'postal_code', 'notes', 'created_at',
                            ];

                            fputcsv($handle, array_merge(
                                $standardHeaders,
                                $customDefs->pluck('label')->toArray()
                            ));

                            $query->orderBy('created_at')->each(function (Contact $contact) use ($handle, $customDefs) {
                                $standardValues = [
                                    $contact->first_name,
                                    $contact->last_name,
                                    $contact->email,
                                    $contact->phone,
                                    $contact->address_line_1,
                                    $contact->address_line_2,
                                    $contact->city,
                                    $contact->state,
                                    $contact->postal_code,
                                    $contact->notes,
                                    $contact->created_at?->toDateTimeString(),
                                ];

                                $customValues = $customDefs
                                    ->map(fn ($def) => $contact->custom_fields[$def->handle] ?? '')
                                    ->toArray();

                                fputcsv($handle, array_merge($standardValues, $customValues));
                            });

                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
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

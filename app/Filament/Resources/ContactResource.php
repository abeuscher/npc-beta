<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Forms\Components\TagSelect;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\PortalAccount;
use App\Services\QuickBooks\QuickBooksAuth;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_contact') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Group::make([

                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->columnSpan(6),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->columnSpan(6),

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

                        Forms\Components\TextInput::make('prefix')
                            ->label('Prefix')
                            ->placeholder('Mr, Ms, Dr…')
                            ->columnSpan(4),

                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Date of Birth')
                            ->maxDate(now()->subYears(13)->toDateString())
                            ->helperText('Must be 13 or older — see the help article for details.')
                            ->columnSpan(4),

                        Forms\Components\Placeholder::make('age')
                            ->label('Age')
                            ->content(fn (?Contact $record): string => $record?->date_of_birth
                                ? $record->date_of_birth->age . ' years'
                                : '—'
                            )
                            ->columnSpan(4),
                    ])
                    ->columns(12),

                Forms\Components\Section::make('Custom Fields')
                    ->schema(fn () => CustomFieldDef::forModel('contact')->get()
                        ->map(fn ($def) => $def->toFilamentFormComponent())
                        ->toArray()
                    )
                    ->columns(2)
                    ->hidden(fn () => CustomFieldDef::forModel('contact')->doesntExist()),

            ])->columnSpan(2),

            Forms\Components\Group::make([

                Forms\Components\Section::make('Settings')
                    ->schema([
                        TagSelect::make('contact'),

                        Forms\Components\Placeholder::make('source_display')
                            ->label('Source')
                            ->content(fn (?Contact $record): string => match ($record?->source ?? 'manual') {
                                'import'    => 'Import',
                                'api'       => 'API',
                                'web_form'  => 'Web Form',
                                default     => 'Manual Entry',
                            }),

                        Forms\Components\Toggle::make('do_not_contact')
                            ->label('Do Not Contact'),

                        Forms\Components\Toggle::make('mailing_list_opt_in')
                            ->label('Mailing List Opt-In'),
                    ]),

                Forms\Components\Section::make('Portal Access')
                    ->collapsible()
                    ->schema(function (?Contact $record): array {
                    if (! $record) {
                        return [];
                    }

                    $portal = PortalAccount::where('email', $record->email)->first();

                    if (! $portal) {
                        return [
                            Forms\Components\Placeholder::make('portal_none')
                                ->label('')
                                ->content('No portal account for this contact.'),
                        ];
                    }

                    $status = match (true) {
                        ! $portal->is_active             => 'Suspended',
                        $portal->email_verified_at === null => 'Unverified',
                        default                          => 'Active',
                    };

                    return [
                        Forms\Components\Placeholder::make('portal_status')
                            ->label('Status')
                            ->content($status),

                        Forms\Components\Placeholder::make('portal_email')
                            ->label('Portal Email')
                            ->content($portal->email),

                        Forms\Components\Placeholder::make('portal_created_at')
                            ->label('Account Created')
                            ->content($portal->created_at->format('F j, Y')),
                    ];
                }),

                Forms\Components\Section::make('Household')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('household_id')
                            ->label('Household Head')
                            ->placeholder('Solo — no household')
                            ->helperText('Select another contact to make them the head of this contact\'s household. Clear the field to make this contact solo.')
                            ->options(fn (?Contact $record) => Contact::when(
                                $record,
                                fn ($q) => $q->where('id', '!=', $record->id)
                            )->orderByRaw("COALESCE(last_name, first_name)")->get()
                                ->mapWithKeys(fn ($c) => [$c->id => $c->display_name . ($c->email ? ' — ' . $c->email : '')]))
                            ->searchable()
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('Affiliation')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('organization_id')
                            ->label('Organization')
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('QuickBooks')
                    ->collapsible()
                    ->schema(function (?Contact $record): array {
                        if (! $record) {
                            return [];
                        }

                        if (filled($record->quickbooks_customer_id)) {
                            return [
                                Forms\Components\Placeholder::make('qb_status')
                                    ->label('Status')
                                    ->content("Linked — QB Customer #{$record->quickbooks_customer_id}"),
                            ];
                        }

                        return [
                            Forms\Components\Placeholder::make('qb_status')
                                ->label('Status')
                                ->content('Not linked'),
                        ];
                    })
                    ->hidden(fn () => ! app(QuickBooksAuth::class)->isConnected()),

            ])->columnSpan(1),

        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => static::getUrl('edit', ['record' => $record]))
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
                    ->getStateUsing(function (Contact $record): array {
                        $roles = [];
                        if ($record->memberships()->where('status', 'active')->exists()) {
                            $roles[] = 'Member';
                        }
                        if ($record->donations()->exists()) {
                            $roles[] = 'Donor';
                        }
                        if (empty($roles)) {
                            $roles[] = 'Contact';
                        }
                        return $roles;
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Member'  => 'success',
                        'Donor'   => 'warning',
                        'Contact' => 'gray',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('household_display')
                    ->label('Household')
                    ->getStateUsing(function (Contact $record): ?string {
                        if (! $record->household_id || $record->household_id === $record->id) {
                            return null;
                        }

                        return $record->householdName();
                    })
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

                Tables\Filters\Filter::make('is_member')
                    ->label('Members only')
                    ->query(fn ($query) => $query->isMember()),

                Tables\Filters\Filter::make('is_donor')
                    ->label('Donors only')
                    ->query(fn ($query) => $query->isDonor()),

                Tables\Filters\Filter::make('in_household')
                    ->label('In a household')
                    ->query(fn ($query) => $query->whereColumn('household_id', '!=', 'id')),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc')
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
            ])
            ->modifyQueryUsing(fn ($query) => $query->with([
                'head',
                'memberships' => fn ($q) => $q->where('status', 'active'),
                'donations',
            ]));
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ContactResource\RelationManagers\MembershipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit'   => Pages\EditContact::route('/{record}/edit'),
            'notes'  => Pages\ContactNotes::route('/{record}/notes'),
        ];
    }
}

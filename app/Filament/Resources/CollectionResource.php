<?php

namespace App\Filament\Resources;

/*
 * Collections marked is_public = true will be queryable from public-facing page components
 * in session 007+. Never mark a collection as public if it contains personal, financial,
 * or membership-related data. CRM entities (Contacts, Memberships, Donations, Organizations)
 * are architecturally excluded from the collection system and cannot be surfaced publicly
 * through this mechanism regardless of settings.
 *
 * Payment data is strictly one-directional: CMS → Payment → CRM. Financial transaction
 * records, registrant data, and donor history must never be queried from or displayed by
 * the CMS layer under any circumstances. System collections (blog_posts, events) expose
 * display-only data shapes; their underlying financial and relational records remain
 * exclusively in the CRM and payment systems.
 *
 * Collections are not routable. The 'handle' is a unique machine identifier used to
 * reference a collection in widgets — not a URL segment.
 */

use App\Filament\Resources\CollectionResource\Pages;
use App\Filament\Resources\CollectionResource\RelationManagers\CollectionItemsRelationManager;
use App\Models\Collection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class CollectionResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }


    protected static ?string $model = Collection::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Collection Manager';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Collection Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                        if ($operation === 'create') {
                            $set('handle', Str::slug($state, '_'));
                        }
                    }),

                Forms\Components\TextInput::make('handle')
                    ->label('Handle')
                    ->required()
                    ->maxLength(255)
                    ->unique(Collection::class, 'handle', ignoreRecord: true)
                    ->rules(['alpha_dash'])
                    ->helperText('Unique identifier used to reference this collection in widgets. Not a URL.')
                    ->disabled(fn (Forms\Get $get, string $operation): bool =>
                        $operation === 'edit' && $get('source_type') !== 'custom'
                    )
                    ->dehydrated()
                    ->rule(function (Forms\Get $get) {
                        return function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                            if ($get('source_type') === 'custom' && in_array($value, Collection::RESERVED_HANDLES, true)) {
                                $fail('This handle is reserved for a system data source.');
                            }
                        };
                    }),

                Forms\Components\TextInput::make('source_type')
                    ->label('Source Type')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('System collections are backed by a dedicated model, not the generic item store.')
                    ->visible(fn (string $operation): bool => $operation === 'edit'),

                Forms\Components\Textarea::make('description')
                    ->nullable()
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_public')
                    ->label('Public')
                    ->helperText(
                        'Only public collections can be queried from public-facing components. ' .
                        'Do not enable for collections containing personal, financial, or membership data.'
                    ),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Fields')
                ->description('Define the data fields for items in this collection.')
                ->visible(fn (Forms\Get $get, string $operation): bool =>
                    $operation === 'create' || $get('source_type') === 'custom'
                )
                ->schema([
                    Forms\Components\Repeater::make('fields')
                        ->schema([
                            Forms\Components\TextInput::make('key')
                                ->required()
                                ->rules(['alpha_dash'])
                                ->helperText('Lowercase with underscores. Used in widgets as item.key'),

                            Forms\Components\TextInput::make('label')
                                ->required(),

                            Forms\Components\Select::make('type')
                                ->required()
                                ->live()
                                ->options([
                                    'text'      => 'Text',
                                    'textarea'  => 'Textarea',
                                    'rich_text' => 'Rich Text',
                                    'number'    => 'Number',
                                    'date'      => 'Date',
                                    'toggle'    => 'Toggle',
                                    'image'     => 'Image',
                                    'url'       => 'URL',
                                    'email'     => 'Email',
                                    'select'    => 'Select',
                                ])
                                ->default('text'),

                            Forms\Components\Toggle::make('required')
                                ->label('Required')
                                ->default(false),

                            Forms\Components\TextInput::make('helpText')
                                ->label('Help Text')
                                ->nullable(),

                            Forms\Components\Repeater::make('options')
                                ->label('Select Options')
                                ->schema([
                                    Forms\Components\TextInput::make('value')->required(),
                                    Forms\Components\TextInput::make('label')->required(),
                                ])
                                ->visible(fn (Forms\Get $get): bool => $get('type') === 'select')
                                ->defaultItems(0)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
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

                Tables\Columns\TextColumn::make('handle')
                    ->label('Handle'),

                Tables\Columns\BadgeColumn::make('source_type')
                    ->label('Source')
                    ->colors([
                        'gray' => 'custom',
                        'info' => fn ($state) => $state !== 'custom',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'custom'     => 'Custom',
                        'blog_posts' => 'Blog Posts',
                        'events'     => 'Events',
                        default      => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('fields_count')
                    ->label('Fields')
                    ->getStateUsing(function (Collection $record): string {
                        if ($record->isSystemCollection()) {
                            return '—';
                        }

                        return (string) count($record->fields ?? []);
                    }),

                Tables\Columns\BadgeColumn::make('is_public')
                    ->label('Visibility')
                    ->formatStateUsing(fn ($state) => $state ? 'Public' : 'Private')
                    ->colors([
                        'success' => true,
                        'gray'    => false,
                    ]),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('collection_items_count')
                    ->label('Items')
                    ->getStateUsing(function (Collection $record): string {
                        if ($record->isSystemCollection()) {
                            return '—';
                        }

                        return (string) $record->collection_items_count;
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Collection $record): bool => ! $record->isSystemCollection()),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Collection $record): bool => ! $record->isSystemCollection())
                    ->modalDescription(fn (Collection $record): ?string =>
                        $record->collectionItems()->exists()
                            ? "This collection has {$record->collectionItems()->count()} item(s). Deleting it will also remove all items."
                            : null
                    ),

                Tables\Actions\RestoreAction::make()
                    ->visible(fn (Collection $record): bool => ! $record->isSystemCollection()),

                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn (Collection $record): bool => ! $record->isSystemCollection())
                    ->modalDescription(fn (Collection $record): ?string =>
                        $record->collectionItems()->withTrashed()->exists()
                            ? "This collection has {$record->collectionItems()->withTrashed()->count()} item(s) that will be permanently lost."
                            : null
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->recordUrl(fn (Collection $record): ?string =>
                $record->isSystemCollection()
                    ? null
                    : static::getUrl('edit', ['record' => $record])
            );
    }

    public static function getRelationManagers(): array
    {
        return [
            CollectionItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCollections::route('/'),
            'create' => Pages\CreateCollection::route('/create'),
            'edit'   => Pages\EditCollection::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->withCount('collectionItems');
    }
}

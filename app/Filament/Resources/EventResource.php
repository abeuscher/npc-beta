<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers;
use App\Models\Event;
use App\Forms\Components\UsStateSelect;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Events';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Split::make([
                // ── Left column ───────────────────────────────────────────
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Description')->schema([
                        Forms\Components\RichEditor::make('description')
                            ->hiddenLabel()
                            ->nullable(),
                    ]),

                    Forms\Components\Section::make('Address')->schema([
                        Forms\Components\TextInput::make('address_line_1')
                            ->label('Address line 1')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('address_line_2')
                            ->label('Address line 2')
                            ->maxLength(255),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('city')
                                ->maxLength(100),

                            UsStateSelect::make('state'),

                            Forms\Components\TextInput::make('zip')
                                ->maxLength(20),
                        ]),

                        Forms\Components\Placeholder::make('_map_sep')
                            ->hiddenLabel()
                            ->content(new HtmlString('<hr class="border-gray-200 -mx-6 -mt-2">')),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('map_label')
                                ->label('Map button label')
                                ->maxLength(255)
                                ->placeholder('e.g. View on Google Maps'),

                            Forms\Components\TextInput::make('map_url')
                                ->label('Map link')
                                ->url()
                                ->maxLength(2048),
                        ]),
                    ]),

                    Forms\Components\Section::make('Online Meeting Info')->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('meeting_label')
                                ->label('Link label')
                                ->maxLength(255)
                                ->placeholder('e.g. Join on Zoom'),

                            Forms\Components\TextInput::make('meeting_url')
                                ->label('Meeting link')
                                ->url()
                                ->maxLength(2048),
                        ]),

                        Forms\Components\RichEditor::make('meeting_details')
                            ->label('Joining Details')
                            ->nullable(),
                    ]),
                ]),

                // ── Right column ──────────────────────────────────────────
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Settings')->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Event::class, 'slug', ignoreRecord: true)
                            ->regex('/^[a-z0-9\-]+$/')
                            ->helperText('URL-safe identifier. Auto-generated from title on create.'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft'     => 'Draft',
                                'published' => 'Published',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),

                        Forms\Components\TextInput::make('capacity')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('Leave blank for unlimited capacity.'),

                        Forms\Components\Toggle::make('registration_open')
                            ->label('Registration open')
                            ->default(true),

                        Forms\Components\TextInput::make('price')
                            ->label('Ticket price')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText('Set to 0 for a free event.'),
                    ]),

                    Forms\Components\Section::make('Dates')->schema([
                        Forms\Components\Repeater::make('eventDates')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                Forms\Components\DateTimePicker::make('starts_at')
                                    ->label('Start')
                                    ->required()
                                    ->seconds(false),

                                Forms\Components\DateTimePicker::make('ends_at')
                                    ->label('End')
                                    ->seconds(false)
                                    ->after('starts_at'),

                                Forms\Components\Hidden::make('status')
                                    ->default('inherited'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add date')
                            ->defaultItems(0)
                            ->reorderable(false),
                    ]),
                ])->grow(false),
            ])->from('md')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'draft',
                        'success' => 'published',
                        'danger'  => 'cancelled',
                    ]),

                Tables\Columns\IconColumn::make('is_free')
                    ->label('Free')
                    ->boolean(),

                Tables\Columns\TextColumn::make('event_dates_count')
                    ->label('Dates')
                    ->counts('eventDates')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Draft',
                        'published' => 'Published',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EventRegistrationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit'   => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}

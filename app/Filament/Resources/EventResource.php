<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
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
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Description')->schema([
                        Forms\Components\RichEditor::make('description')
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                    Forms\Components\Section::make('Location')->schema([
                        Forms\Components\Toggle::make('is_in_person')
                            ->label('In-person attendance')
                            ->default(true)
                            ->live(),

                        Forms\Components\TextInput::make('address_line_1')
                            ->maxLength(255)
                            ->hidden(fn (Forms\Get $get) => ! $get('is_in_person')),

                        Forms\Components\TextInput::make('address_line_2')
                            ->maxLength(255)
                            ->hidden(fn (Forms\Get $get) => ! $get('is_in_person')),

                        Forms\Components\TextInput::make('city')
                            ->maxLength(100)
                            ->hidden(fn (Forms\Get $get) => ! $get('is_in_person')),

                        Forms\Components\TextInput::make('state')
                            ->maxLength(100)
                            ->hidden(fn (Forms\Get $get) => ! $get('is_in_person')),

                        Forms\Components\TextInput::make('zip')
                            ->maxLength(20)
                            ->hidden(fn (Forms\Get $get) => ! $get('is_in_person')),

                        Forms\Components\TextInput::make('map_url')
                            ->label('Map URL')
                            ->url()
                            ->maxLength(2048)
                            ->hidden(fn (Forms\Get $get) => ! $get('is_in_person')),

                        Forms\Components\TextInput::make('map_label')
                            ->label('Map Link Label')
                            ->maxLength(255)
                            ->placeholder('e.g. View on Google Maps')
                            ->hidden(fn (Forms\Get $get) => ! $get('is_in_person')),

                        Forms\Components\Toggle::make('is_virtual')
                            ->label('Virtual / Online attendance')
                            ->live(),

                        Forms\Components\TextInput::make('meeting_url')
                            ->label('Meeting URL')
                            ->url()
                            ->maxLength(2048)
                            ->hidden(fn (Forms\Get $get) => ! $get('is_virtual')),
                    ])->columns(2),
                ])->columnSpan(2),

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

                        Forms\Components\Toggle::make('is_free')
                            ->label('Free event')
                            ->default(true),

                        Forms\Components\TextInput::make('capacity')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('Leave blank for unlimited capacity.'),

                        Forms\Components\Toggle::make('registration_open')
                            ->label('Registration open')
                            ->default(true),
                    ]),

                    Forms\Components\Section::make('Recurrence')->schema([
                        Forms\Components\Toggle::make('is_recurring')
                            ->label('Recurring event')
                            ->live(),

                        Forms\Components\Select::make('recurrence_type')
                            ->options([
                                'manual' => 'Manual — pick dates individually',
                                'rule'   => 'Rule-based — generate from pattern',
                            ])
                            ->nullable()
                            ->hidden(fn (Forms\Get $get) => ! $get('is_recurring'))
                            ->helperText('Dates are managed in the Dates relation manager below.'),
                    ]),
                ])->columnSpan(1),
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
            RelationManagers\EventDatesRelationManager::class,
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

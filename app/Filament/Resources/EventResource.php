<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers;
use App\Models\Event;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\WidgetType;
use App\Forms\Components\QuillEditor;
use App\Forms\Components\UsStateSelect;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Events';

    protected static ?int $navigationSort = 5;

    // ──────────────────────────────────────────────────────────────────────────
    // Shared: create the standard landing page for an event
    // ──────────────────────────────────────────────────────────────────────────

    public static function createLandingPageForEvent(Event $event): void
    {
        if ($event->landing_page_id) {
            return; // already has one
        }

        $autoPublish = SiteSetting::get('event_auto_publish', 'false') === 'true';

        $page = Page::create([
            'title'        => $event->title,
            'is_published' => $autoPublish,
            'published_at' => $autoPublish ? now() : null,
            'type'         => 'event',
        ]);

        // Override the auto-generated slug to include the events/ prefix.
        $page->update(['slug' => 'events/' . $event->slug]);

        $widgetHandles = ['event_description', 'event_dates', 'event_registration'];
        $sort = 1;

        foreach ($widgetHandles as $handle) {
            $widgetType = WidgetType::where('handle', $handle)->first();

            if (! $widgetType) {
                continue;
            }

            PageWidget::create([
                'page_id'        => $page->id,
                'widget_type_id' => $widgetType->id,
                'label'          => $widgetType->label,
                'config'         => ['event_id' => $event->id],
                'sort_order'     => $sort++,
                'is_active'      => true,
            ]);
        }

        $event->update(['landing_page_id' => $page->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Split::make([
                // ── Left column ───────────────────────────────────────────
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Description')->schema([
                        QuillEditor::make('description')
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

                        QuillEditor::make('meeting_details')
                            ->label('Joining Details')
                            ->nullable(),
                    ]),
                ]),

                // ── Right column ──────────────────────────────────────────
                Forms\Components\Group::make([
                    Forms\Components\Section::make('Settings')->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Event::class, 'slug', ignoreRecord: true)
                            ->regex('/^[a-z0-9\-]+$/')
                            ->helperText('URL-safe identifier. Auto-generated from title on create.')
                            ->hiddenOn('create'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft'     => 'Draft',
                                'published' => 'Published',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),

                        // Read-only landing page status with edit/view shortcuts.
                        // Hidden on create — the landing page is auto-created on save.
                        Forms\Components\Placeholder::make('landing_page_status')
                            ->label('Landing page')
                            ->hiddenOn('create')
                            ->content(function ($record): string {
                                if (! $record || ! $record->landing_page_id) {
                                    return 'None — use the "Create basic landing page" button in the toolbar.';
                                }
                                return $record->landingPage?->title ?? '(page not found)';
                            })
                            ->hintActions([
                                FormAction::make('editLandingPage')
                                    ->icon('heroicon-m-pencil-square')
                                    ->tooltip('Edit page in CMS')
                                    ->url(function ($record): string {
                                        if (! $record?->landingPage) {
                                            return '';
                                        }
                                        return PageResource::getUrl('edit', ['record' => $record->landingPage]);
                                    })
                                    ->visible(fn ($record) => $record?->landing_page_id !== null),

                                FormAction::make('viewLandingPage')
                                    ->icon('heroicon-m-arrow-top-right-on-square')
                                    ->tooltip('View public page')
                                    ->url(function ($record): string {
                                        if (! $record?->landingPage) {
                                            return '';
                                        }
                                        return url('/' . $record->landingPage->slug);
                                    })
                                    ->openUrlInNewTab()
                                    ->visible(fn ($record) => $record?->landing_page_id !== null),
                            ]),
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
                            ->reorderable(false)
                            ->deleteAction(
                                fn ($action) => $action->iconButton()->icon('heroicon-m-trash')
                            ),
                    ]),

                    Forms\Components\Section::make('Registration Details')->schema([
                        Forms\Components\Select::make('registration_mode')
                            ->options([
                                'open'     => 'Open — accepting registrations',
                                'closed'   => 'Closed — at capacity or paused',
                                'none'     => 'No registration required (walk-in / public event)',
                                'external' => 'External — handled on another website',
                            ])
                            ->default('open')
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('external_registration_url')
                            ->label('External registration URL')
                            ->url()
                            ->nullable()
                            ->helperText('Registrants will be redirected to this URL.')
                            ->visible(fn (Get $get) => $get('registration_mode') === 'external'),

                        Forms\Components\TextInput::make('capacity')
                            ->numeric()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('Leave blank for unlimited capacity.')
                            ->disabled(fn (Get $get) => in_array($get('registration_mode'), ['external', 'none'])),

                        Forms\Components\TextInput::make('price')
                            ->label('Ticket price')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->helperText('Set to 0 for a free event.')
                            ->disabled(fn (Get $get) => in_array($get('registration_mode'), ['external', 'none'])),

                        Forms\Components\Toggle::make('auto_create_contacts')
                            ->label('Automatically add registrants to Contacts')
                            ->default(true)
                            ->helperText('Creates or updates a Contact record for each new registrant.')
                            ->disabled(fn (Get $get) => in_array($get('registration_mode'), ['external', 'none'])),

                        Forms\Components\Toggle::make('mailing_list_opt_in_enabled')
                            ->label('Show mailing list opt-in checkbox on registration form')
                            ->default(false)
                            ->helperText('Adds an opt-in checkbox to the public registration form.')
                            ->disabled(fn (Get $get) => in_array($get('registration_mode'), ['external', 'none'])),
                    ]),
                ])->grow(false),
            ])->from('md')->columnSpanFull(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────────────────────────────────

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

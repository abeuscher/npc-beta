<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\Template;
use App\Models\WidgetType;
use App\Forms\Components\QuillEditor;
use App\Forms\Components\UsStateSelect;
use App\Traits\HasPageBuilderForm;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends Resource
{
    use HasPageBuilderForm;

    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Events';

    protected static ?int $navigationSort = 3;

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if ($user && ! $user->can('delete_event')) {
            return false;
        }

        return $record->registrations()->doesntExist();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Shared: compute ends_at from duration fields
    // ──────────────────────────────────────────────────────────────────────────

    public static function computeEndsAt(array $data): array
    {
        if (! empty($data['start_date'])) {
            $hour   = (int) ($data['start_hour'] ?? 12);
            $minute = (int) ($data['start_minute'] ?? 0);
            $ampm   = $data['start_ampm'] ?? 'AM';

            // Convert 12-hour to 24-hour
            if ($ampm === 'AM' && $hour === 12) {
                $hour = 0;
            } elseif ($ampm === 'PM' && $hour !== 12) {
                $hour += 12;
            }

            $start = \Carbon\Carbon::parse($data['start_date'])
                ->setTime($hour, $minute, 0);

            $data['starts_at'] = $start->format('Y-m-d H:i:s');

            if (! empty($data['all_day'])) {
                $data['ends_at'] = $start->copy()->addDay()->format('Y-m-d H:i:s');
            } else {
                $dh = (int) ($data['duration_hours'] ?? 1);
                $dm = (int) ($data['duration_minutes'] ?? 0);
                $data['ends_at'] = $start->copy()->addHours($dh)->addMinutes($dm)->format('Y-m-d H:i:s');
            }
        }

        unset($data['start_date'], $data['start_hour'], $data['start_minute'], $data['start_ampm'], $data['time_separator'], $data['time_spacer']);
        unset($data['duration_hours'], $data['duration_minutes'], $data['all_day']);

        return $data;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Shared: create the standard landing page for an event
    // ──────────────────────────────────────────────────────────────────────────

    public static function createLandingPageForEvent(Event $event): void
    {
        if ($event->landing_page_id) {
            return; // already has one
        }

        $autoPublish = SiteSetting::get('event_auto_publish', 'true') === 'true';

        $page = Page::create([
            'title'        => $event->title,
            'status'       => $autoPublish ? 'published' : 'draft',
            'published_at' => $autoPublish ? now() : null,
            'type'         => 'event',
            'author_id'    => $event->author_id,
        ]);

        // Override the auto-generated slug to include the events/ prefix.
        $page->update(['slug' => 'events/' . $event->slug]);

        // Try to hydrate widgets from the "Event Landing Page" content template.
        $template = Template::where('name', 'Event Landing Page')
            ->where('type', 'content')
            ->first();

        if ($template && $template->widgets()->exists()) {
            \App\Models\PageWidget::copyOwnedStack($template, $page);

            // Stamp the event slug onto every copied widget's config.
            foreach ($page->widgets()->get() as $widget) {
                $config = $widget->config ?? [];
                $config['event_slug'] = $event->slug;
                $widget->update(['config' => $config]);
            }
        } else {
            // Fall back to hardcoded handles if template is missing or empty.
            $fallbacks = [
                ['handle' => 'event_description',  'sort_order' => 1],
                ['handle' => 'event_registration', 'sort_order' => 2],
            ];

            foreach ($fallbacks as $spec) {
                $widgetType = WidgetType::where('handle', $spec['handle'])->first();
                if (! $widgetType) {
                    continue;
                }

                $page->widgets()->create([
                    'widget_type_id'    => $widgetType->id,
                    'label'             => $widgetType->label,
                    'config'            => ['event_slug' => $event->slug],
                    'appearance_config' => \App\Models\PageWidget::resolveAppearance([], $spec['handle']),
                    'sort_order'        => $spec['sort_order'],
                    'is_active'         => true,
                ]);
            }
        }

        $event->update(['landing_page_id' => $page->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema(
            static::pageBuilderFormSchema(
                type: 'event',
                modelType: 'event',
                tagType: 'event',
                uniqueSections: [
                    Forms\Components\Grid::make(3)->schema([
                        // ── Left column (8 of 12 = 2 of 3) ──────────────────
                        Forms\Components\Group::make([
                            Forms\Components\Section::make('Event Details')->schema([
                                Forms\Components\Grid::make(12)->schema([
                                    Forms\Components\Fieldset::make('Start Time')
                                        ->id('start-time-fieldset')
                                        ->schema([
                                        Forms\Components\DatePicker::make('start_date')
                                            ->label('Date / Time')
                                            ->required()
                                            ->columnSpan(4),

                                        Forms\Components\Select::make('start_hour')
                                            ->label("\u{200B}")
                                            ->options(collect(range(1, 12))->mapWithKeys(fn ($h) => [$h => $h]))
                                            ->default(12)
                                            ->required()
                                            ->columnSpan(3),

                                        Forms\Components\Placeholder::make('time_separator')
                                            ->label("\u{200B}")
                                            ->content(new HtmlString('<span style="font-weight:700;font-size:1.25rem;line-height:2.4rem;display:flex;justify-content:center;">:</span>'))
                                            ->columnSpan(1),

                                        Forms\Components\Select::make('start_minute')
                                            ->label("\u{200B}")
                                            ->options(collect(range(0, 59))->mapWithKeys(fn ($m) => [$m => str_pad($m, 2, '0', STR_PAD_LEFT)]))
                                            ->default(0)
                                            ->required()
                                            ->columnSpan(3),

                                        Forms\Components\Select::make('start_ampm')
                                            ->extraAttributes(['class' => 'gap-0'])
                                            ->label("\u{200B}")
                                            ->options(['AM' => 'am', 'PM' => 'pm'])
                                            ->default('AM')
                                            ->required()
                                            ->columnSpan(1),
                                    ])->columns(12)->columnSpan(7),

                                    Forms\Components\Fieldset::make('Duration')->schema([
                                        Forms\Components\Select::make('duration_hours')
                                            ->label('Hours')
                                            ->options(collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => $h]))
                                            ->default(1)
                                            ->required()
                                            ->disabled(fn (Forms\Get $get): bool => (bool) $get('all_day')),

                                        Forms\Components\Select::make('duration_minutes')
                                            ->label('Minutes')
                                            ->options([0 => '00', 15 => '15', 30 => '30', 45 => '45'])
                                            ->default(0)
                                            ->required()
                                            ->disabled(fn (Forms\Get $get): bool => (bool) $get('all_day')),

                                        Forms\Components\Toggle::make('all_day')
                                            ->label('All day')
                                            ->live(),
                                    ])->columns(3)->columnSpan(5),
                                ]),

                                Forms\Components\Hidden::make('starts_at'),
                                Forms\Components\Hidden::make('ends_at'),

                                QuillEditor::make('description')
                                    ->label('Description')
                                    ->nullable(),

                                SpatieMediaLibraryFileUpload::make('event_thumbnail')
                                    ->label('Thumbnail image')
                                    ->helperText('Used in events listing widgets.')
                                    ->collection('event_thumbnail')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                    ->nullable()
                                    ->columnSpanFull(),
                            ]),

                            Forms\Components\Section::make('Location Info')
                                ->collapsed()
                                ->schema([
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

                            Forms\Components\Section::make('Online Meeting Info')
                                ->collapsed()
                                ->schema([
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
                        ])->columnSpan(2),

                        // ── Right column (4 of 12 = 1 of 3) ─────────────────
                        Forms\Components\Section::make('Registration Details')
                            ->columnSpan(1)
                            ->schema([
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
                    ]),
                ],
                imageFields: [
                    SpatieMediaLibraryFileUpload::make('event_thumbnail')
                        ->label('Thumbnail image')
                        ->helperText('Used in event listing widgets and social sharing.')
                        ->collection('event_thumbnail')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('event_header')
                        ->label('Header image')
                        ->helperText('Optional banner image displayed at the top of the event page.')
                        ->collection('event_header')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('event_og_image')
                        ->label('Open Graph image')
                        ->helperText('Used for social sharing previews.')
                        ->collection('event_og_image')
                        ->disk('public')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),
                ],
                withSeo: false,
                pageBuilderProps: null,
            )
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Table
    // ──────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => static::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:ia')
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
            ->defaultSort('starts_at', 'asc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Event $record): bool => $record->registrations()->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            abort_unless(auth()->user()?->can('delete_event'), 403);

                            $records->each(function (Event $record) {
                                if ($record->registrations()->doesntExist()) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'         => Pages\ListEvents::route('/'),
            'create'        => Pages\CreateEvent::route('/create'),
            'edit'          => Pages\EditEvent::route('/{record}/edit'),
            'registrations' => Pages\ViewRegistrations::route('/{record}/registrations'),
        ];
    }
}

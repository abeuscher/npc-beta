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
use App\Traits\HasPageBuilderForm;
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
    use HasPageBuilderForm;

    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Events';

    protected static ?int $navigationSort = 3;

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

        $widgetHandles = ['event_description', 'event_registration'];
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
                'config'         => ['event_slug' => $event->slug],
                'sort_order'     => $sort++,
                'is_active'      => true,
            ]);
        }

        $event->update(['landing_page_id' => $page->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Form
    // ──────────────────────────────────────────────────────────────────────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_event') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema(
            static::pageBuilderFormSchema(
                type: 'event',
                modelType: 'event',
                tagType: 'event',
                uniqueSections: [
                    Forms\Components\Section::make('Event Details')->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\DateTimePicker::make('starts_at')
                                ->label('Start')
                                ->required()
                                ->seconds(false),

                            Forms\Components\DateTimePicker::make('ends_at')
                                ->label('End')
                                ->nullable()
                                ->seconds(false)
                                ->after('starts_at'),
                        ]),

                        QuillEditor::make('description')
                            ->label('Description')
                            ->nullable(),
                    ]),
                    Forms\Components\Section::make('Location Info')->schema([
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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

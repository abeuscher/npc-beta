<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavigationItemResource\Pages;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Post;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NavigationItemResource extends Resource
{
    protected static ?string $model = NavigationItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Navigation';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([

                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('link_type')
                    ->label('Link type')
                    ->options([
                        'page' => 'Internal page',
                        'link' => 'Link',
                    ])
                    ->default('page')
                    ->live()
                    ->afterStateHydrated(function (Forms\Components\Select $component, $record): void {
                        if (! $record) {
                            $component->state('page');
                            return;
                        }

                        if ($record->page_id || $record->post_id) {
                            $component->state('page');
                            return;
                        }

                        $knownPrefixes = [
                            '/' . config('site.blog_prefix', 'news'),
                            '/' . config('site.events_prefix', 'events'),
                        ];

                        if ($record->url && in_array($record->url, $knownPrefixes, true)) {
                            $component->state('page');
                            return;
                        }

                        $component->state('link');
                    }),

                Forms\Components\Select::make('internal_page')
                    ->label('Page')
                    ->options(fn () => static::internalPageOptions())
                    ->afterStateHydrated(function (Forms\Components\Select $component, $record): void {
                        if (! $record) {
                            return;
                        }

                        if ($record->page_id) {
                            $component->state('page:' . $record->page_id);
                        } elseif ($record->post_id) {
                            $component->state('post:' . $record->post_id);
                        } elseif ($record->url) {
                            $component->state('url:' . $record->url);
                        }
                    })
                    ->searchable()
                    ->visible(fn (Forms\Get $get) => $get('link_type') === 'page'),

                Forms\Components\TextInput::make('url')
                    ->label('Link')
                    ->nullable()
                    ->visible(fn (Forms\Get $get) => $get('link_type') === 'link'),

                Forms\Components\Select::make('parent_id')
                    ->label('Parent item')
                    ->relationship('parent', 'label')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),

                Forms\Components\Group::make([
                    Forms\Components\Toggle::make('is_visible')
                        ->label('Visible')
                        ->default(true),

                    Forms\Components\Toggle::make('open_in_new_window')
                        ->label('Open in new window?')
                        ->default(false)
                        ->afterStateHydrated(function (Forms\Components\Toggle $component, $record): void {
                            $component->state($record?->target === '_blank');
                        }),
                ]),

            ])->columns(3),
        ]);
    }

    /**
     * Build the grouped options list for the internal page selector.
     * Keys are prefixed so resolveFormData() can decode them to the right column.
     */
    public static function internalPageOptions(): array
    {
        $blogPrefix   = config('site.blog_prefix', 'news');
        $eventsPrefix = config('site.events_prefix', 'events');

        $options = [
            'Site indexes' => [
                'url:/' . $blogPrefix   => 'Blog index  (/' . $blogPrefix . ')',
                'url:/' . $eventsPrefix => 'Events index  (/' . $eventsPrefix . ')',
            ],
        ];

        $pages = Page::orderBy('title')->get(['id', 'title']);
        if ($pages->isNotEmpty()) {
            $options['Pages'] = $pages
                ->mapWithKeys(fn ($p) => ['page:' . $p->id => $p->title])
                ->all();
        }

        $posts = Post::orderBy('title')->get(['id', 'title']);
        if ($posts->isNotEmpty()) {
            $options['Posts'] = $posts
                ->mapWithKeys(fn ($p) => ['post:' . $p->id => $p->title])
                ->all();
        }

        return $options;
    }

    /**
     * Translate virtual form fields into the real model columns.
     * Called from both CreateNavigationItem and EditNavigationItem.
     */
    public static function resolveFormData(array $data): array
    {
        // open_in_new_window toggle → target column
        $data['target'] = ($data['open_in_new_window'] ?? false) ? '_blank' : '_self';

        // link_type + internal_page → page_id / post_id / url
        $data['page_id'] = null;
        $data['post_id'] = null;

        if (($data['link_type'] ?? 'page') === 'page') {
            $data['url'] = null;
            $key = $data['internal_page'] ?? '';

            if (str_starts_with($key, 'page:')) {
                $data['page_id'] = substr($key, 5);
            } elseif (str_starts_with($key, 'post:')) {
                $data['post_id'] = substr($key, 5);
            } elseif (str_starts_with($key, 'url:')) {
                $data['url'] = substr($key, 4);
            }
        }
        // link type: url comes from the TextInput as-is; page_id/post_id already nulled above

        unset($data['link_type'], $data['internal_page'], $data['open_in_new_window']);

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.label')
                    ->label('Parent')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('page.title')
                    ->label('Page')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('url')
                    ->label('Link')
                    ->placeholder('—')
                    ->limit(40),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNavigationItems::route('/'),
            'create' => Pages\CreateNavigationItem::route('/create'),
            'edit'   => Pages\EditNavigationItem::route('/{record}/edit'),
        ];
    }
}

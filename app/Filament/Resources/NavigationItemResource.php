<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavigationItemResource\Pages;
use App\Models\NavigationItem;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NavigationItemResource extends Resource
{
    protected static ?string $model = NavigationItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = 'CMS';

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
                        $component->state($record->page_id ? 'page' : 'link');
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
     * Grouped options for the internal page selector.
     * Keys are prefixed so resolveFormData() can decode them to the right column.
     * Order: Pages → Events → Blog
     */
    public static function internalPageOptions(): array
    {
        $options = [];

        $pages = Page::where('type', '!=', 'event')
            ->where('type', '!=', 'post')
            ->orderBy('title')
            ->get(['id', 'title']);

        if ($pages->isNotEmpty()) {
            $options['Pages'] = $pages
                ->mapWithKeys(fn ($p) => ['page:' . $p->id => $p->title])
                ->all();
        }

        $eventPages = Page::where('type', 'event')
            ->orderBy('title')
            ->get(['id', 'title']);

        if ($eventPages->isNotEmpty()) {
            $options['Events'] = $eventPages
                ->mapWithKeys(fn ($p) => ['page:' . $p->id => $p->title])
                ->all();
        }

        $postPages = Page::where('type', 'post')
            ->orderBy('title')
            ->get(['id', 'title']);

        if ($postPages->isNotEmpty()) {
            $options['Blog'] = $postPages
                ->mapWithKeys(fn ($p) => ['page:' . $p->id => $p->title])
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
        $data['target']  = ($data['open_in_new_window'] ?? false) ? '_blank' : '_self';
        $data['page_id'] = null;

        if (($data['link_type'] ?? 'page') === 'page') {
            $data['url'] = null;
            $key = $data['internal_page'] ?? '';

            if (str_starts_with($key, 'page:')) {
                $data['page_id'] = substr($key, 5);
            }
        }

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
            ->defaultSort('sort_order')
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

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNavigationItems::route('/'),
            'create' => Pages\CreateNavigationItem::route('/create'),
            'edit'   => Pages\EditNavigationItem::route('/{record}/edit'),
        ];
    }
}

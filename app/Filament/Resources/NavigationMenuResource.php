<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavigationMenuResource\Pages;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NavigationMenuResource extends Resource
{
    protected static ?string $model = NavigationMenu::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Navigation';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_navigation_item') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_navigation_item') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('update_navigation_item') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_navigation_item') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Menu details')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('handle')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->rules(['alpha_dash'])
                        ->helperText('Used in templates and settings to identify this menu')
                        ->maxLength(255),
                ])
                ->columns(2),

            Forms\Components\Section::make('Links')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->schema([
                            ...static::itemSchema(),
                            Forms\Components\Repeater::make('children')
                                ->schema(static::itemSchema())
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                ->addActionLabel('Add child link')
                                ->defaultItems(0),
                        ])
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->defaultItems(0),
                ]),
        ]);
    }

    private static function itemSchema(): array
    {
        return [
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->required(),

                    Forms\Components\Select::make('link_type')
                        ->options(['page' => 'Internal page', 'url' => 'URL'])
                        ->default('page')
                        ->live(),

                    Forms\Components\Select::make('internal_page')
                        ->label('Page')
                        ->options(fn () => static::internalPageOptions())
                        ->searchable()
                        ->visible(fn (Forms\Get $get) => $get('link_type') === 'page'),

                    Forms\Components\TextInput::make('url')
                        ->label('URL')
                        ->nullable()
                        ->visible(fn (Forms\Get $get) => $get('link_type') === 'url'),
                ]),

            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Toggle::make('open_in_new_window')
                        ->default(false),

                    Forms\Components\Toggle::make('is_visible')
                        ->default(true),
                ]),
        ];
    }

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

    public static function itemToFormArray(NavigationItem $item): array
    {
        return [
            'link_type'          => $item->page_id ? 'page' : 'url',
            'internal_page'      => $item->page_id ? 'page:' . $item->page_id : null,
            'url'                => $item->url,
            'label'              => $item->label,
            'open_in_new_window' => $item->target === '_blank',
            'is_visible'         => $item->is_visible,
        ];
    }

    public static function resolveItemData(array $data): array
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

        unset($data['link_type'], $data['internal_page'], $data['open_in_new_window'], $data['children']);

        return $data;
    }

    public static function saveItems(NavigationMenu $menu, array $items): void
    {
        NavigationItem::where('navigation_menu_id', $menu->id)->delete();

        foreach ($items as $sortOrder => $itemData) {
            $children = $itemData['children'] ?? [];
            $resolved = static::resolveItemData($itemData);

            $parent = NavigationItem::create(array_merge($resolved, [
                'navigation_menu_id' => $menu->id,
                'parent_id'          => null,
                'sort_order'         => $sortOrder,
            ]));

            foreach ($children as $childSortOrder => $childData) {
                $resolvedChild = static::resolveItemData($childData);

                NavigationItem::create(array_merge($resolvedChild, [
                    'navigation_menu_id' => $menu->id,
                    'parent_id'          => $parent->id,
                    'sort_order'         => $childSortOrder,
                ]));
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => static::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('handle')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('This menu may be in use on your site. Deleting it will remove it from any pages where it appears.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withCount('items');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNavigationMenus::route('/'),
            'create' => Pages\CreateNavigationMenu::route('/create'),
            'edit'   => Pages\EditNavigationMenu::route('/{record}/edit'),
        ];
    }
}

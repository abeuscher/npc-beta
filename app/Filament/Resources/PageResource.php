<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Livewire\PageBuilder;
use App\Models\CustomFieldDef;
use App\Models\Page;
use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Placeholder::make('_slug_placeholder')
                    ->label('')
                    ->content('')
                    ->hiddenOn('edit'),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(Page::class, 'slug', ignoreRecord: true)
                    ->rules(['regex:/^[a-z0-9\-\/]+$/'])
                    ->notIn(['admin', 'horizon', 'up', 'login', 'logout', 'register'])
                    ->helperText('URL-safe identifier. May include forward slashes (e.g. events/my-event).')
                    ->hiddenOn('create'),

                Forms\Components\Hidden::make('type')
                    ->default('default'),

                Forms\Components\Placeholder::make('public_url')
                    ->label('Public URL')
                    ->content(function ($record): HtmlString|string {
                        if (! $record) {
                            return '—';
                        }
                        $base = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
                        $path = $record->slug === 'home' ? '/' : '/' . $record->slug;
                        $url  = $base . $path;

                        return new HtmlString(
                            '<a href="' . e($url) . '" target="_blank" rel="noopener" ' .
                            'class="text-primary-600 hover:underline text-sm font-mono">' .
                            e($url) . '</a> ' .
                            '<span class="text-xs text-gray-400">(saves slug changes first)</span>'
                        );
                    })
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Page Builder')
                ->description('Add and arrange content blocks for this page.')
                ->schema([
                    Forms\Components\Livewire::make(
                        PageBuilder::class,
                        fn ($record) => $record ? ['pageId' => $record->id] : []
                    )->columnSpanFull(),
                ])
                ->hidden(fn ($record) => $record === null)
                ->columnSpanFull(),

            Forms\Components\Section::make('Publication')
                ->schema([
                    Forms\Components\Toggle::make('is_published')
                        ->label('Published')
                        ->live()
                        ->afterStateUpdated(function (bool $state, Forms\Set $set, Forms\Get $get) {
                            if ($state && ! $get('published_at')) {
                                $set('published_at', now());
                            }
                        }),

                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Publish Date')
                        ->visible(fn (Forms\Get $get) => $get('is_published')),
                ])->columns(2),

            Forms\Components\Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('meta_title')
                        ->maxLength(255)
                        ->helperText('Defaults to page title if blank.'),

                    Forms\Components\Textarea::make('meta_description')
                        ->rows(3)
                        ->maxLength(160),
                ])
                ->columns(1)
                ->collapsible()
                ->collapsed(),

            Forms\Components\Section::make('Custom Fields')
                ->schema(fn () => CustomFieldDef::forModel('page')->get()
                    ->map(fn ($def) => $def->toFilamentFormComponent())
                    ->toArray()
                )
                ->columns(2)
                ->hidden(fn () => CustomFieldDef::forModel('page')->doesntExist()),
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('type', '!=', 'event');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published')
                    ->trueLabel('Published only')
                    ->falseLabel('Unpublished only'),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit'   => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Forms\Components\TagSelect;
use App\Livewire\PageBuilder;
use App\Models\CustomFieldDef;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class PostResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?string $navigationLabel = 'Blog Posts';

    protected static ?string $modelLabel = 'Post';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('type', 'post');
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_post') ?? false;
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_post') ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_post') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Group::make([

                Forms\Components\Group::make([
                    Forms\Components\Section::make()->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                'regex:/^[a-z0-9\-\/]+$/',
                                fn (?Model $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                    $prefix   = SiteSetting::get('blog_prefix', 'news') . '/';
                                    $fullSlug = $prefix . ltrim($value, '/');
                                    $query    = Page::where('slug', $fullSlug);
                                    if ($record?->id) {
                                        $query->where('id', '!=', $record->id);
                                    }
                                    if ($query->exists()) {
                                        $fail('This slug is already in use.');
                                    }
                                },
                            ])
                            ->prefix(fn (): string => '/' . SiteSetting::get('blog_prefix', 'news') . '/')
                            ->formatStateUsing(fn ($state): string =>
                                ltrim(str_replace(SiteSetting::get('blog_prefix', 'news') . '/', '', $state ?? ''), '/')
                            )
                            ->dehydrateStateUsing(fn ($state): string =>
                                SiteSetting::get('blog_prefix', 'news') . '/' . ltrim($state ?? '', '/')
                            )
                            ->helperText('Edit the slug segment after the blog prefix.')
                            ->hiddenOn('create'),

                        Forms\Components\Hidden::make('type')
                            ->default('post'),

                        Forms\Components\Placeholder::make('public_url')
                            ->label('Public URL')
                            ->content(function ($record): HtmlString|string {
                                if (! $record) {
                                    return '—';
                                }
                                $base = rtrim(SiteSetting::get('base_url', config('app.url')), '/');
                                $url  = $base . '/' . $record->slug;

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
                        ->description('Add and arrange content blocks for this post.')
                        ->schema([
                            Forms\Components\Livewire::make(
                                PageBuilder::class,
                                fn ($record) => $record ? ['pageId' => $record->id] : []
                            )->columnSpanFull(),
                        ])
                        ->hidden(fn ($record) => $record === null)
                        ->columnSpanFull(),

                    Forms\Components\Section::make('SEO')
                        ->schema([
                            Forms\Components\TextInput::make('meta_title')
                                ->maxLength(255)
                                ->helperText('Defaults to post title if blank.'),

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

                ])->columnSpan(2),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Select::make('author_id')
                            ->label('Author')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->required()
                            ->default(fn () => auth()->id()),

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

                        TagSelect::make('post'),
                    ])
                    ->columnSpan(1),

            ])->columns(3)->columnSpanFull(),
        ]);
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

                Tables\Filters\TrashedFilter::make(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit'   => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}

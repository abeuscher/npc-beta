<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Forms\Components\TagSelect;
use App\Livewire\PageBuilder;
use App\Models\CustomFieldDef;
use App\Models\Page;
use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'CMS';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_page') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->type !== 'system' && (auth()->user()?->can('delete_page') ?? false);
    }

    public static function canRestore(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_page') ?? false;
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('delete_page') ?? false;
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

                        // Shown on create only — editable type selection.
                        Forms\Components\Select::make('type')
                            ->label('Page Type')
                            ->options([
                                'default' => 'Web Page',
                                'member'  => 'Member Page',
                            ])
                            ->default('default')
                            ->hiddenOn('edit'),

                        // Shown on edit only for system pages — read-only type label.
                        Forms\Components\Placeholder::make('type_display')
                            ->label('Page Type')
                            ->content('System Page')
                            ->visibleOn('edit')
                            ->hidden(fn (Forms\Get $get): bool => $get('type') !== 'system'),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                'regex:/^[a-z0-9\-\/]+$/',
                                fn (Forms\Get $get, ?Model $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    $type   = $get('type');
                                    $prefix = match ($type) {
                                        'post'  => SiteSetting::get('blog_prefix', 'news') . '/',
                                        'event' => SiteSetting::get('events_prefix', 'events') . '/',
                                        default => '',
                                    };
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
                            ->notIn(['admin', 'horizon', 'up', 'login', 'logout', 'register'])
                            ->prefix(fn (Forms\Get $get): ?string => match ($get('type')) {
                                'member' => '/' . SiteSetting::get('portal_prefix', 'members') . '/',
                                'post'   => '/' . SiteSetting::get('blog_prefix', 'news') . '/',
                                'event'  => '/' . SiteSetting::get('events_prefix', 'events') . '/',
                                default  => null,
                            })
                            ->formatStateUsing(fn ($state, Forms\Get $get): string => match ($get('type')) {
                                'member' => ltrim(str_replace(SiteSetting::get('portal_prefix', 'members') . '/', '', $state ?? ''), '/'),
                                'post'   => ltrim(str_replace(SiteSetting::get('blog_prefix', 'news') . '/', '', $state ?? ''), '/'),
                                'event'  => ltrim(str_replace(SiteSetting::get('events_prefix', 'events') . '/', '', $state ?? ''), '/'),
                                default  => $state ?? '',
                            })
                            ->dehydrateStateUsing(fn ($state, Forms\Get $get): string => match ($get('type')) {
                                'post'  => SiteSetting::get('blog_prefix', 'news') . '/' . ltrim($state ?? '', '/'),
                                'event' => SiteSetting::get('events_prefix', 'events') . '/' . ltrim($state ?? '', '/'),
                                default => $state ?? '',
                            })
                            ->disabled(fn (Forms\Get $get): bool => $get('type') === 'member')
                            ->dehydrated()
                            ->helperText(fn (Forms\Get $get) => match ($get('type')) {
                                'member' => 'Slug is locked — member page slugs are managed by the portal prefix setting.',
                                'post'   => 'Edit the slug segment after the blog prefix.',
                                'event'  => 'Edit the slug segment after the events prefix.',
                                default  => 'URL-safe identifier. May include forward slashes (e.g. events/my-event).',
                            })
                            ->hidden(fn (string $operation, Forms\Get $get): bool => $operation === 'create' || $get('type') === 'system'),

                        // System pages: show the full stored slug as read-only text.
                        Forms\Components\Placeholder::make('system_slug_display')
                            ->label('Slug')
                            ->content(fn ($record): string => $record?->slug ?? '—')
                            ->helperText('Slug is locked — system page slugs can only be changed via the System Pages Prefix setting.')
                            ->visibleOn('edit')
                            ->hidden(fn (Forms\Get $get): bool => $get('type') !== 'system'),

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

                ])->columnSpan(2),

                Forms\Components\Section::make('Settings')
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

                        TagSelect::make('page'),
                    ])
                    ->columnSpan(1),

            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('type', '!=', 'event')
            ->where('type', '!=', 'post');
    }

    public static function resolveRecordRouteBinding(int | string $key): ?\Illuminate\Database\Eloquent\Model
    {
        // getEloquentQuery() excludes event/post pages to keep the CMS list clean,
        // but that scope also blocks direct edits of event landing pages.
        // Resolve records without the type filter so any page type can be edited directly.
        return \App\Models\Page::where('id', $key)->first();
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

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'gray'    => 'default',
                        'info'    => 'post',
                        'warning' => 'event',
                        'success' => 'member',
                        'danger'  => 'system',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'post'    => 'Post',
                        'event'   => 'Event',
                        'member'  => 'Member',
                        'system'  => 'System',
                        default   => 'Page',
                    }),

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

                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->multiple()
                    ->options([
                        'default' => 'Page',
                        'post'    => 'Post',
                        'event'   => 'Event',
                        'member'  => 'Member',
                        'system'  => 'System',
                    ])
                    ->default(['default']),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->filtersFormColumns(3)
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Page $record): bool => $record->type === 'system'),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make()
                    ->hidden(fn (Page $record): bool => $record->type === 'system'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Page $record) {
                                if ($record->type !== 'system') {
                                    $record->delete();
                                }
                            });
                        }),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Page $record) {
                                if ($record->type !== 'system') {
                                    $record->forceDelete();
                                }
                            });
                        }),
                ]),
            ]);
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

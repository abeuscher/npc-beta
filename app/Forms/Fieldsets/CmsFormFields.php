<?php

namespace App\Forms\Fieldsets;

use App\Models\Event;
use App\Models\Page;
use App\Models\SiteSetting;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;

class CmsFormFields
{
    public static function titleField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('title')
            ->label('Page Name')
            ->required()
            ->maxLength(255);
    }

    public static function authorField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('author_id')
            ->label('Author')
            ->relationship('author', 'name')
            ->searchable()
            ->required()
            ->default(fn () => auth()->id());
    }

    /**
     * @param  string  $type  'page', 'post', or 'event'
     */
    public static function statusField(string $type): Forms\Components\Select
    {
        $statusOptions = $type === 'event'
            ? ['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled']
            : ['draft' => 'Draft', 'published' => 'Published'];

        return Forms\Components\Select::make('status')
            ->options($statusOptions)
            ->default('draft')
            ->required()
            ->live()
            ->afterStateUpdated(function (string $state, Forms\Set $set, Forms\Get $get) {
                if ($state === 'published' && ! $get('published_at')) {
                    $set('published_at', now());
                }
            });
    }

    public static function publishedAtField(): Forms\Components\DateTimePicker
    {
        return Forms\Components\DateTimePicker::make('published_at')
            ->label('Publish Date')
            ->visible(fn (Forms\Get $get) => $get('status') === 'published');
    }

    /**
     * Build the slug TextInput for the given content type.
     */
    public static function slugField(string $type): Forms\Components\TextInput
    {
        if ($type === 'event') {
            return Forms\Components\TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(Event::class, 'slug', ignoreRecord: true)
                ->regex('/^[a-z0-9\-]+$/')
                ->hiddenOn('create');
        }

        if ($type === 'post') {
            return Forms\Components\TextInput::make('slug')
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
                ->hiddenOn('create');
        }

        // page — full prefix logic based on page type
        return Forms\Components\TextInput::make('slug')
            ->required()
            ->maxLength(255)
            ->rules([
                'regex:/^[a-z0-9\-\/]+$/',
                fn (Forms\Get $get, ?Model $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                    $pageType = $get('type');
                    $prefix = match ($pageType) {
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
                default  => null,
            })
            ->hidden(fn (string $operation, Forms\Get $get): bool => $operation === 'create' || $get('type') === 'system');
    }
}

<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Event;
use App\Models\Form;
use App\Models\Page;
use App\Models\Product;

class PageBuilderDataSources
{
    /**
     * Resolve a named data source to a [value => label] options array.
     * Returns an empty array for unknown source names.
     */
    public static function resolve(string $source): array
    {
        return match ($source) {
            'events'      => static::events(),
            'products'    => static::products(),
            'forms'       => static::forms(),
            'collections' => static::collections(),
            'pages'       => static::pages(),
            default       => [],
        };
    }

    private static function events(): array
    {
        return Event::published()
            ->orderBy('starts_at')
            ->get(['slug', 'title'])
            ->pluck('title', 'slug')
            ->all();
    }

    private static function products(): array
    {
        return Product::where('status', 'published')
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->pluck('name', 'slug')
            ->all();
    }

    private static function forms(): array
    {
        return Form::where('is_active', true)
            ->orderBy('title')
            ->get(['handle', 'title'])
            ->pluck('title', 'handle')
            ->all();
    }

    private static function collections(): array
    {
        return Collection::where('is_active', true)
            ->orderBy('name')
            ->get(['handle', 'name'])
            ->pluck('name', 'handle')
            ->all();
    }

    private static function pages(): array
    {
        return Page::published()
            ->where('type', 'default')
            ->orderBy('title')
            ->get(['slug', 'title'])
            ->pluck('title', 'slug')
            ->all();
    }
}

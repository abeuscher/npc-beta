<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Event;
use App\Models\Page;
use App\Models\Product;

class WidgetDataResolver
{
    /**
     * Resolve data for a collection handle.
     *
     * @param  array{limit?: int}  $queryConfig
     * @return array<int, array<string, mixed>>
     */
    public static function resolve(
        string $handle,
        array $queryConfig = []
    ): array {
        $collection = Collection::where('handle', $handle)
            ->public()
            ->first();

        if (! $collection) {
            return [];
        }

        return match ($collection->source_type) {
            'blog_posts' => static::resolveBlogPosts($queryConfig),
            'events'     => static::resolveEvents($queryConfig),
            'products'   => static::resolveProducts($queryConfig),
            default      => [],
        };
    }

    private static function resolveBlogPosts(array $queryConfig): array
    {
        $limit = isset($queryConfig['limit']) ? (int) $queryConfig['limit'] : null;

        $query = Page::where('type', 'post')
            ->published()
            ->with('media')
            ->orderBy('published_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (Page $page) => [
            'id'            => $page->id,
            'title'         => $page->title,
            'slug'          => $page->slug,
            'published_at'  => $page->published_at?->toIso8601String(),
            'thumbnail_url' => $page->getFirstMediaUrl('post_thumbnail', 'webp') ?: $page->getFirstMediaUrl('post_thumbnail'),
        ])->all();
    }

    private static function resolveEvents(array $queryConfig): array
    {
        $limit        = isset($queryConfig['limit']) ? (int) $queryConfig['limit'] : null;
        $eventsPrefix = config('site.events_prefix', 'events');

        $query = Event::with(['landingPage', 'media'])
            ->published()
            ->upcoming()
            ->orderBy('starts_at', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (Event $event) => [
            'id'            => $event->id,
            'title'         => $event->title,
            'slug'          => $event->slug,
            'starts_at'     => $event->starts_at->toIso8601String(),
            'ends_at'       => $event->ends_at?->toIso8601String(),
            'is_virtual'    => $event->is_virtual,
            'is_free'       => $event->is_free,
            'url'           => $event->landingPage
                ? url('/' . $event->landingPage->slug)
                : url('/' . $eventsPrefix),
            'thumbnail_url' => $event->getFirstMediaUrl('event_thumbnail', 'webp') ?: $event->getFirstMediaUrl('event_thumbnail'),
        ])->all();
    }

    public static function resolveProducts(array $queryConfig = []): array
    {
        $limit = isset($queryConfig['limit']) ? (int) $queryConfig['limit'] : null;

        $query = Product::with(['prices', 'media'])
            ->where('status', 'published')
            ->where('is_archived', false)
            ->orderBy('sort_order', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (Product $product) => [
            'id'          => $product->id,
            'name'        => $product->name,
            'slug'        => $product->slug,
            'description' => $product->description,
            'capacity'    => $product->capacity,
            'available'   => max(0, $product->capacity - $product->purchases()->where('status', 'active')->count()),
            'image_url'   => $product->getFirstMediaUrl('product_image', 'webp') ?: $product->getFirstMediaUrl('product_image'),
            'prices'      => $product->prices->map(fn ($price) => [
                'id'              => $price->id,
                'label'           => $price->label,
                'amount'          => $price->amount,
                'stripe_price_id' => $price->stripe_price_id,
            ])->all(),
        ])->all();
    }
}

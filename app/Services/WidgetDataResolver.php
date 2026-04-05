<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Event;
use App\Models\Page;
use App\Models\Product;

class WidgetDataResolver
{
    /**
     * Resolve data for a collection handle.
     *
     * @param  array{limit?: int, order_by?: string, direction?: string, include_tags?: string[], exclude_tags?: string[]}  $queryConfig
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
            'custom'     => static::resolveCustom($collection->id, $queryConfig),
            'blog_posts' => static::resolveBlogPosts($queryConfig),
            'events'     => static::resolveEvents($queryConfig),
            'products'   => static::resolveProducts($queryConfig),
            default      => [],
        };
    }

    private static function resolveCustom(string $collectionId, array $queryConfig): array
    {
        $limit       = isset($queryConfig['limit']) ? (int) $queryConfig['limit'] : null;
        $orderBy     = $queryConfig['order_by'] ?? 'sort_order';
        $direction   = $queryConfig['direction'] ?? 'asc';
        $includeTags = $queryConfig['include_tags'] ?? [];
        $excludeTags = $queryConfig['exclude_tags'] ?? [];

        // Validate orderBy against safe column list to prevent injection.
        $allowedOrderBy = array_merge(
            ['sort_order', 'created_at', 'updated_at'],
            static::getCollectionFieldKeys($collectionId)
        );

        if (! in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'sort_order';
        }

        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $query = CollectionItem::where('collection_id', $collectionId)
            ->where('is_published', true)
            ->with('media')
            ->orderBy($orderBy, $direction);

        if ($limit) {
            $query->limit($limit);
        }

        if (! empty($includeTags)) {
            $query->whereHas('cmsTags', fn ($q) => $q->whereIn('slug', $includeTags));
        }

        if (! empty($excludeTags)) {
            $query->whereDoesntHave('cmsTags', fn ($q) => $q->whereIn('slug', $excludeTags));
        }

        // Identify image fields so we can include media objects in the result.
        $imageFieldKeys = static::getCollectionImageFieldKeys($collectionId);

        return $query->get()->map(function (CollectionItem $item) use ($imageFieldKeys) {
            $row = $item->data ?? [];
            foreach ($imageFieldKeys as $fieldKey) {
                $row['_media'][$fieldKey] = $item->getFirstMedia($fieldKey);
            }
            return $row;
        })->all();
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

    /**
     * Return the JSON field keys for the collection (for orderBy allowlist).
     */
    private static function getCollectionFieldKeys(string $collectionId): array
    {
        $collection = Collection::find($collectionId);

        if (! $collection) {
            return [];
        }

        return collect($collection->fields ?? [])
            ->pluck('key')
            ->filter()
            ->all();
    }

    /**
     * Return field keys that are image-type for a given collection.
     */
    private static function getCollectionImageFieldKeys(string $collectionId): array
    {
        $collection = Collection::find($collectionId);

        if (! $collection) {
            return [];
        }

        return collect($collection->fields ?? [])
            ->where('type', 'image')
            ->pluck('key')
            ->filter()
            ->all();
    }
}

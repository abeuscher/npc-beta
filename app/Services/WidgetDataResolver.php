<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Post;

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
            'events'     => [], // Placeholder — resolves when Event model exists
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

        return $query->pluck('data')->all();
    }

    private static function resolveBlogPosts(array $queryConfig): array
    {
        $limit = isset($queryConfig['limit']) ? (int) $queryConfig['limit'] : null;

        $query = Post::where('is_published', true)
            ->orderBy('published_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (Post $post) => [
            'id'           => $post->id,
            'title'        => $post->title,
            'slug'         => $post->slug,
            'excerpt'      => $post->excerpt,
            'content'      => $post->content,
            'published_at' => $post->published_at?->toIso8601String(),
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
}

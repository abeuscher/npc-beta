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
     * @return array<int, array<string, mixed>>
     */
    public static function resolve(
        string $handle,
        ?int $limit = null,
        string $orderBy = 'sort_order',
        string $direction = 'asc'
    ): array {
        $collection = Collection::where('handle', $handle)
            ->public()
            ->first();

        if (! $collection) {
            return [];
        }

        return match ($collection->source_type) {
            'custom'      => static::resolveCustom($collection->id, $limit, $orderBy, $direction),
            'blog_posts'  => static::resolveBlogPosts($limit),
            'events'      => [], // Placeholder — resolves when Event model exists
            default       => [],
        };
    }

    private static function resolveCustom(
        string $collectionId,
        ?int $limit,
        string $orderBy,
        string $direction
    ): array {
        $query = CollectionItem::where('collection_id', $collectionId)
            ->where('is_published', true)
            ->orderBy($orderBy, $direction);

        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('data')->all();
    }

    private static function resolveBlogPosts(?int $limit): array
    {
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
}

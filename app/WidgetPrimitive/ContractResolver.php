<?php

namespace App\WidgetPrimitive;

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Page;
use App\Services\PageContextTokens;
use Illuminate\Support\Str;

final class ContractResolver
{
    public function __construct(
        private readonly PageContextTokens $pageContextTokens,
    ) {}

    /**
     * Resolve a list of contracts into a list of DTOs, indexed to match the input.
     *
     * Batching: a per-call cache deduplicates fetches when multiple contracts
     * address the same underlying source (same system model + filter shape, or
     * same collection handle). Two carousels pointing at the same collection
     * hit the database once.
     *
     * Fail-closed: only fields declared on the contract appear in the returned
     * DTO. Missing data yields an empty string for scalars, an empty array for
     * row-sets.
     *
     * @param  array<int, DataContract>  $contracts
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $contracts, SlotContext $context): array
    {
        $cache = [];
        $results = [];

        foreach ($contracts as $i => $contract) {
            $results[$i] = match ($contract->source) {
                DataContract::SOURCE_PAGE_CONTEXT        => $this->resolvePageContext($contract, $context),
                DataContract::SOURCE_SYSTEM_MODEL        => $this->resolveSystemModel($contract, $context, $cache),
                DataContract::SOURCE_WIDGET_CONTENT_TYPE => $this->resolveWidgetContentType($contract, $context, $cache),
                default                                  => [],
            };
        }

        return $results;
    }

    /**
     * Scalar-map DTO of page-context tokens, restricted to declared fields.
     *
     * @return array<string, string>
     */
    private function resolvePageContext(DataContract $contract, SlotContext $context): array
    {
        $page = $context->currentPage();

        $all = $page ? $this->pageContextTokens->values($page) : [];

        $dto = [];
        foreach ($contract->fields as $field) {
            $dto[$field] = (string) ($all[$field] ?? '');
        }
        return $dto;
    }

    /**
     * Row-set DTO of a typed system model. Only 'post' is wired up in this prototype.
     *
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveSystemModel(DataContract $contract, SlotContext $context, array &$cache): array
    {
        if ($contract->model !== 'post') {
            return ['items' => []];
        }

        $key = 'post:' . sha1(serialize($contract->filters));
        if (! array_key_exists($key, $cache)) {
            $query = Page::where('type', 'post')
                ->published()
                ->with('media')
                ->orderByRaw('COALESCE(published_at, created_at) DESC');

            if (! empty($contract->filters['limit'])) {
                $query->limit((int) $contract->filters['limit']);
            }

            $cache[$key] = $query->get();
        }

        $blogPrefix = config('site.blog_prefix', 'news');
        $rows = $cache[$key]->map(function (Page $post) use ($contract, $blogPrefix) {
            $full = $this->projectPost($post, $blogPrefix);

            $row = [];
            foreach ($contract->fields as $field) {
                $row[$field] = $full[$field] ?? '';
            }
            return $row;
        })->values()->all();

        return ['items' => $rows];
    }

    /**
     * Flat row shape derived from a Page model. Each row-field is a value the
     * contract may request; fields outside this map are not exposed.
     *
     * @return array<string, mixed>
     */
    private function projectPost(Page $post, string $blogPrefix): array
    {
        $thumb = $post->getFirstMediaUrl('post_thumbnail', 'webp')
            ?: $post->getFirstMediaUrl('post_thumbnail');

        return [
            'id'       => $post->id,
            'title'    => $post->title,
            'slug'     => $post->slug,
            'url'      => url('/' . $post->slug),
            'date'     => $post->published_at?->format('F j, Y') ?? '',
            'date_iso' => $post->published_at?->toIso8601String() ?? '',
            'excerpt'  => Str::limit(strip_tags($post->meta_description ?? ''), 160),
            'image'    => $thumb,
        ];
    }

    /**
     * Row-set DTO for a widget-declared content type. The widget owns the
     * schema; the resolver reads rows from the named collection and projects
     * them through the widget's declared fields.
     *
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveWidgetContentType(DataContract $contract, SlotContext $context, array &$cache): array
    {
        $handle = $contract->resourceHandle;
        if ($handle === null || $handle === '' || $contract->contentType === null) {
            return ['items' => []];
        }

        $key = 'collection:' . $handle;
        if (! array_key_exists($key, $cache)) {
            $collection = CmsCollection::where('handle', $handle)->public()->first();
            if ($collection === null) {
                $cache[$key] = collect();
            } else {
                $cache[$key] = CollectionItem::where('collection_id', $collection->id)
                    ->where('is_published', true)
                    ->with('media')
                    ->orderBy('sort_order')
                    ->get();
            }
        }

        $imageKeys = $contract->contentType->imageFieldKeys();

        $rows = $cache[$key]->map(function (CollectionItem $item) use ($contract, $imageKeys) {
            $data = $item->data ?? [];
            $row = [];
            foreach ($contract->fields as $field) {
                $row[$field] = $data[$field] ?? '';
            }
            if ($imageKeys !== []) {
                $media = [];
                foreach ($imageKeys as $imageKey) {
                    $media[$imageKey] = $item->getFirstMedia($imageKey);
                }
                $row['_media'] = $media;
            }
            return $row;
        })->values()->all();

        return ['items' => $rows];
    }
}

<?php

namespace App\WidgetPrimitive;

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Page;
use App\WidgetPrimitive\Projectors\PageContextProjector;
use App\WidgetPrimitive\Projectors\SystemModelProjector;
use App\WidgetPrimitive\Projectors\WidgetContentTypeProjector;

final class ContractResolver
{
    public function __construct(
        private readonly PageContextProjector $pageContextProjector,
        private readonly SystemModelProjector $systemModelProjector,
        private readonly WidgetContentTypeProjector $widgetContentTypeProjector,
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
     * Fallback: for SOURCE_WIDGET_CONTENT_TYPE contracts that resolve to an
     * empty item-set, the resolver looks up fallback rows in $fallback —
     * keyed by resourceHandle first, then by any non-empty entry for
     * back-compat with the widget-type-slot keying (e.g. 'slides'). Fallback
     * rows are projected through the same field filter as live items, so
     * undeclared fields never leak into the DTO.
     *
     * @param  array<int, DataContract>  $contracts
     * @param  array<string, array<int, array<string, mixed>>>  $fallback
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $contracts, SlotContext $context, array $fallback = []): array
    {
        $cache = [];
        $results = [];

        foreach ($contracts as $i => $contract) {
            $results[$i] = match ($contract->source) {
                DataContract::SOURCE_PAGE_CONTEXT        => $this->pageContextProjector->project($contract, $context->currentPage()),
                DataContract::SOURCE_SYSTEM_MODEL        => $this->resolveSystemModel($contract, $cache),
                DataContract::SOURCE_WIDGET_CONTENT_TYPE => $this->resolveWidgetContentType($contract, $cache, $fallback),
                default                                  => [],
            };
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveSystemModel(DataContract $contract, array &$cache): array
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

        return $this->systemModelProjector->project($contract, $cache[$key]);
    }

    /**
     * @param  array<string, mixed>  $cache
     * @param  array<string, array<int, array<string, mixed>>>  $fallback
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveWidgetContentType(DataContract $contract, array &$cache, array $fallback): array
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

        $dto = $this->widgetContentTypeProjector->project($contract, $cache[$key]);

        if (empty($dto['items'])) {
            $fallbackRows = $this->fallbackRowsFor($contract, $fallback);
            if ($fallbackRows !== []) {
                $dto = $this->widgetContentTypeProjector->projectFallback($contract, $fallbackRows);
            }
        }

        return $dto;
    }

    /**
     * Pick fallback rows for a contract: match by resourceHandle first, then
     * the first non-empty entry in $fallback for back-compat with the
     * widget-type slot-name keying the renderer used before Phase 2 of 210.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $fallback
     * @return array<int, array<string, mixed>>
     */
    private function fallbackRowsFor(DataContract $contract, array $fallback): array
    {
        $handle = $contract->resourceHandle;
        if ($handle !== null && $handle !== '' && isset($fallback[$handle]) && is_array($fallback[$handle]) && $fallback[$handle] !== []) {
            return $fallback[$handle];
        }

        foreach ($fallback as $rows) {
            if (is_array($rows) && $rows !== []) {
                return $rows;
            }
        }

        return [];
    }
}

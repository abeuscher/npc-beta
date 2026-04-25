<?php

namespace App\WidgetPrimitive;

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Event;
use App\Models\Page;
use App\WidgetPrimitive\Projectors\PageContextProjector;
use App\WidgetPrimitive\Projectors\SystemModelProjector;
use App\WidgetPrimitive\Projectors\WidgetContentTypeProjector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
     * same collection handle + filter shape). Two carousels pointing at the
     * same collection with the same filters hit the database once.
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
                DataContract::SOURCE_WIDGET_CONTENT_TYPE => $this->resolveWidgetContentType($contract, $context, $cache, $fallback),
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
        return match ($contract->model) {
            'post'  => $this->resolvePost($contract, $cache),
            'event' => $this->resolveEvent($contract, $cache),
            default => ['items' => []],
        };
    }

    /**
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolvePost(DataContract $contract, array &$cache): array
    {
        $key = 'post:' . sha1(serialize($contract->filters));
        if (! array_key_exists($key, $cache)) {
            $query = Page::where('type', 'post')
                ->published()
                ->with('media');

            $rawOrder = (string) ($contract->filters['order_by'] ?? '');
            $rawDirection = (string) ($contract->filters['direction'] ?? '');
            if ($rawOrder === '' && $rawDirection === '') {
                $query->orderByRaw('COALESCE(published_at, created_at) DESC');
            } else {
                [$col, $dir] = $this->resolveOrderBy($contract);
                $query->orderBy($col, $dir);
            }

            $this->applyTagFilters($query, $contract);

            if (! empty($contract->filters['limit'])) {
                $query->limit((int) $contract->filters['limit']);
            }

            $cache[$key] = $query->get();
        }

        return $this->systemModelProjector->project($contract, $cache[$key]);
    }

    /**
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveEvent(DataContract $contract, array &$cache): array
    {
        $key = 'event:' . sha1(serialize($contract->filters));
        if (! array_key_exists($key, $cache)) {
            $query = Event::published()->with(['media', 'landingPage']);

            $dateRange = $contract->filters['date_range'] ?? null;
            if (is_array($dateRange)) {
                $from = isset($dateRange['from']) ? Carbon::parse($dateRange['from']) : null;
                $to   = isset($dateRange['to'])   ? Carbon::parse($dateRange['to'])   : null;
                if ($from && $to) {
                    $query->whereBetween('starts_at', [$from, $to]);
                } elseif ($from) {
                    $query->where('starts_at', '>=', $from);
                } elseif ($to) {
                    $query->where('starts_at', '<=', $to);
                }
            }

            [$col, $dir] = $this->resolveOrderBy($contract);
            $query->orderBy($col, $dir);

            $this->applyTagFilters($query, $contract);

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
    private function resolveWidgetContentType(DataContract $contract, SlotContext $context, array &$cache, array $fallback): array
    {
        $handle = $contract->resourceHandle;
        if ($handle === null || $handle === '' || $contract->contentType === null) {
            return ['items' => []];
        }

        $cacheScope = $context->publicSurface ? 'public' : 'any';
        $key = 'collection:' . $cacheScope . ':' . $handle . ':' . sha1(serialize($contract->filters));
        if (! array_key_exists($key, $cache)) {
            $collQuery = CmsCollection::where('handle', $handle)->where('is_active', true);
            if ($context->publicSurface) {
                $collQuery->where('is_public', true);
            }
            $collection = $collQuery->first();
            if ($collection === null) {
                $cache[$key] = collect();
            } else {
                $itemQuery = CollectionItem::where('collection_id', $collection->id)
                    ->where('is_published', true)
                    ->with('media');

                [$col, $dir] = $this->resolveOrderBy($contract);
                $this->applySwctOrderBy($itemQuery, $col, $dir);

                $this->applyTagFilters($itemQuery, $contract);

                if (! empty($contract->filters['limit'])) {
                    $itemQuery->limit((int) $contract->filters['limit']);
                }

                $cache[$key] = $itemQuery->get();
            }
        }

        $items = $cache[$key];

        $dto = $this->widgetContentTypeProjector->project($contract, $items);

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

    /**
     * Resolve the [column, direction] pair for an ORDER BY, double-gated by
     * the contract's QuerySettings allowlist. Falls back silently to the
     * source-arm default on any unknown user value.
     *
     * Filter shapes accepted (in precedence order):
     *   - filters['order_by'] = 'col dir'   (single string, optional direction)
     *   - filters['order_by'] = 'col' + filters['direction'] = 'asc'|'desc'
     *
     * @return array{0: string, 1: string}
     */
    private function resolveOrderBy(DataContract $contract): array
    {
        $rawOrder = (string) ($contract->filters['order_by'] ?? '');
        $rawDirection = (string) ($contract->filters['direction'] ?? '');

        $col = '';
        $dir = '';
        if ($rawOrder !== '') {
            $parts = explode(' ', trim($rawOrder), 2);
            $col = $parts[0] ?? '';
            $dir = $parts[1] ?? '';
        }
        if ($rawDirection !== '') {
            $dir = $rawDirection;
        }

        $allowed = $contract->querySettings?->orderByOptions ?? [];
        if ($allowed !== [] && ! array_key_exists($col, $allowed)) {
            $col = $contract->orderByDefault();
        } elseif ($col === '') {
            $col = $contract->orderByDefault();
        }

        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        return [$col, $dir];
    }

    /**
     * Apply ORDER BY for a SWCT (CollectionItem) query. System columns sort
     * via `orderBy`; content-type field keys live inside the `data` JSONB
     * column and sort via `data->>'key'`. The column is already allowlisted
     * via querySettings — safe to interpolate after a final regex sanity gate.
     */
    private function applySwctOrderBy(Builder $query, string $col, string $dir): void
    {
        $systemCols = ['sort_order', 'created_at', 'updated_at'];
        if (in_array($col, $systemCols, true)) {
            $query->orderBy($col, $dir);
            return;
        }

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $col)) {
            $query->orderBy('sort_order', $dir);
            return;
        }

        $direction = $dir === 'desc' ? 'desc' : 'asc';
        $query->orderByRaw("data->>'{$col}' {$direction}");
    }

    /**
     * Apply include_tags / exclude_tags filters via the unified `tags()`
     * MorphToMany relation. All five taggable models (Page, Event,
     * CollectionItem, Organization, Contact) declare the same relation shape,
     * so one implementation works for every source-arm.
     */
    private function applyTagFilters(Builder $query, DataContract $contract): void
    {
        if (! ($contract->querySettings?->supportsTags ?? false)) {
            return;
        }

        $include = $contract->filters['include_tags'] ?? null;
        if (is_array($include) && $include !== []) {
            $slugs = array_values(array_filter(array_map('strval', $include), fn ($s) => $s !== ''));
            if ($slugs !== []) {
                $query->whereHas('tags', fn ($q) => $q->whereIn('slug', $slugs));
            }
        }

        $exclude = $contract->filters['exclude_tags'] ?? null;
        if (is_array($exclude) && $exclude !== []) {
            $slugs = array_values(array_filter(array_map('strval', $exclude), fn ($s) => $s !== ''));
            if ($slugs !== []) {
                $query->whereDoesntHave('tags', fn ($q) => $q->whereIn('slug', $slugs));
            }
        }
    }
}

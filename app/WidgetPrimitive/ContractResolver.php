<?php

namespace App\WidgetPrimitive;

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Page;
use App\Models\Product;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use App\WidgetPrimitive\Projectors\PageContextProjector;
use App\WidgetPrimitive\Projectors\RecordContextProjector;
use App\WidgetPrimitive\Projectors\SystemModelProjector;
use App\WidgetPrimitive\Projectors\WidgetContentTypeProjector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class ContractResolver
{
    public function __construct(
        private readonly PageContextProjector $pageContextProjector,
        private readonly RecordContextProjector $recordContextProjector,
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
                DataContract::SOURCE_RECORD_CONTEXT      => $this->recordContextProjector->project($contract, $this->recordFromContext($context)),
                DataContract::SOURCE_SYSTEM_MODEL        => $this->resolveSystemModel($contract, $cache, $context),
                DataContract::SOURCE_WIDGET_CONTENT_TYPE => $this->resolveWidgetContentType($contract, $context, $cache, $fallback),
                default                                  => [],
            };
        }

        return $results;
    }

    private function recordFromContext(SlotContext $context): ?Model
    {
        return $context->ambient instanceof RecordDetailAmbientContext
            ? $context->ambient->record
            : null;
    }

    /**
     * @param  array<string, mixed>  $cache
     * @return array<string, mixed>
     */
    private function resolveSystemModel(DataContract $contract, array &$cache, SlotContext $context): array
    {
        if ($contract->cardinality === DataContract::CARDINALITY_ONE) {
            return match ($contract->model) {
                'event'      => $this->resolveEventOne($contract, $cache),
                'product'    => $this->resolveProductOne($contract, $cache),
                'membership' => $this->resolveMembershipOne($contract, $cache, $context),
                default      => ['item' => null],
            };
        }

        return match ($contract->model) {
            'post'     => $this->resolvePost($contract, $cache),
            'event'    => $this->resolveEvent($contract, $cache),
            'product'  => $this->resolveProduct($contract, $cache),
            'note'     => $this->resolveNote($contract, $cache, $context),
            'donation' => $this->resolveDonationList($contract, $cache, $context),
            default    => ['items' => []],
        };
    }

    /**
     * Resolve a list of Notes attached to the ambient record. Permission gate:
     * fail-closed when the authenticated user lacks `view_note`. Ambient gate:
     * returns empty when the slot is not record-detail. Scoping: notes are
     * filtered by `notable_type` and `notable_id` derived from the ambient
     * record — the contract carries no per-instance scope.
     *
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveNote(DataContract $contract, array &$cache, SlotContext $context): array
    {
        if (! auth()->user()?->can('view_note')) {
            return ['items' => []];
        }

        $record = $this->recordFromContext($context);
        if ($record === null) {
            return ['items' => []];
        }

        $recordType = $record::class;
        $recordId = (string) $record->getKey();

        $key = 'note:' . $recordType . ':' . $recordId . ':' . sha1(serialize($contract->filters));
        if (! array_key_exists($key, $cache)) {
            $allowedOrderBy = ['occurred_at', 'created_at'];
            $rawOrder = (string) ($contract->filters['order_by'] ?? '');
            $col = in_array($rawOrder, $allowedOrderBy, true) ? $rawOrder : 'occurred_at';
            $dir = strtolower((string) ($contract->filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

            $limit = (int) ($contract->filters['limit'] ?? 5);
            if ($limit < 1) {
                $limit = 5;
            }
            if ($limit > 50) {
                $limit = 50;
            }

            $cache[$key] = Note::query()
                ->where('notable_type', $recordType)
                ->where('notable_id', $recordId)
                ->with('author')
                ->orderBy($col, $dir)
                ->take($limit)
                ->get();
        }

        return $this->systemModelProjector->project($contract, $cache[$key]);
    }

    /**
     * Resolve a list of Donations attached to the ambient Contact. Permission
     * gate: fail-closed when the authenticated user lacks `view_donation`.
     * Ambient gate: returns empty when the slot is not record-detail or the
     * ambient record is not a Contact. Status filter: excludes `pending`
     * (checkout-in-flight); includes active/cancelled/past_due history.
     *
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveDonationList(DataContract $contract, array &$cache, SlotContext $context): array
    {
        if (! auth()->user()?->can('view_donation')) {
            return ['items' => []];
        }

        $record = $this->recordFromContext($context);
        if (! $record instanceof Contact) {
            return ['items' => []];
        }

        $allowedOrderBy = ['started_at', 'created_at'];
        $rawOrder = (string) ($contract->filters['order_by'] ?? '');
        $col = in_array($rawOrder, $allowedOrderBy, true) ? $rawOrder : 'started_at';
        $dir = strtolower((string) ($contract->filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $limit = (int) ($contract->filters['limit'] ?? 5);
        if ($limit < 1) {
            $limit = 5;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $key = 'donation:list:' . (string) $record->getKey() . ':' . $col . ':' . $dir . ':' . $limit;
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Donation::query()
                ->where('contact_id', $record->getKey())
                ->whereIn('status', ['active', 'cancelled', 'past_due'])
                ->with('fund')
                ->orderBy($col, $dir)
                ->take($limit)
                ->get();
        }

        return $this->systemModelProjector->project($contract, $cache[$key]);
    }

    /**
     * Resolve a single Event by slug. Aggregate `is_at_capacity` derived via
     * withCount on event_registrations — one coordinated query, no N+1.
     *
     * Per-request slug-keyed cache so EventDescription + EventRegistration
     * targeting the same event landing page hit one query.
     *
     * @param  array<string, mixed>  $cache
     * @return array{item: array<string, mixed>|null}
     */
    private function resolveEventOne(DataContract $contract, array &$cache): array
    {
        $slug = (string) ($contract->filters['slug'] ?? '');
        if ($slug === '') {
            return ['item' => null];
        }

        $key = 'event:one:' . $slug;
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Event::published()
                ->where('slug', $slug)
                ->withCount(['registrations as registered_count' => fn ($q) => $q->whereIn('status', ['pending', 'registered', 'waitlisted', 'attended'])])
                ->with(['media', 'landingPage'])
                ->first();
        }

        return $this->systemModelProjector->projectOne($contract, $cache[$key]);
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
                ->with(['media', 'author']);

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
     * Resolve a list of published, non-archived Products. Aggregate
     * `is_at_capacity` derived via withCount on purchases — one coordinated
     * query, no N+1. Eager-loads `media` and `prices` for the nested DTO
     * projection.
     *
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveProduct(DataContract $contract, array &$cache): array
    {
        $key = 'product:list:' . sha1(serialize($contract->filters));
        if (! array_key_exists($key, $cache)) {
            $query = Product::where('status', 'published')
                ->where('is_archived', false)
                ->withCount(['purchases as active_purchases_count' => fn ($q) => $q->where('status', 'active')])
                ->with(['media', 'prices']);

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
     * Resolve a single Product by slug. Aggregate `is_at_capacity` derived via
     * withCount on purchases. Per-request slug-keyed cache so two ProductDisplay
     * widgets targeting the same product hit one query.
     *
     * @param  array<string, mixed>  $cache
     * @return array{item: array<string, mixed>|null}
     */
    private function resolveProductOne(DataContract $contract, array &$cache): array
    {
        $slug = (string) ($contract->filters['slug'] ?? '');
        if ($slug === '') {
            return ['item' => null];
        }

        $key = 'product:one:' . $slug;
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Product::where('status', 'published')
                ->where('is_archived', false)
                ->where('slug', $slug)
                ->withCount(['purchases as active_purchases_count' => fn ($q) => $q->where('status', 'active')])
                ->with(['media', 'prices'])
                ->first();
        }

        return $this->systemModelProjector->projectOne($contract, $cache[$key]);
    }

    /**
     * Resolve the active Membership attached to the ambient Contact. Permission
     * gate: fail-closed when the authenticated user lacks `view_membership`.
     * Ambient gate: returns null when the slot is not record-detail or the
     * ambient record is not a Contact. Filters to `status = 'active'` only —
     * pending / expired / cancelled rows do not appear; multi-row history is
     * out of scope for this widget.
     *
     * @param  array<string, mixed>  $cache
     * @return array{item: array<string, mixed>|null}
     */
    private function resolveMembershipOne(DataContract $contract, array &$cache, SlotContext $context): array
    {
        if (! auth()->user()?->can('view_membership')) {
            return ['item' => null];
        }

        $record = $this->recordFromContext($context);
        if (! $record instanceof Contact) {
            return ['item' => null];
        }

        $key = 'membership:one:' . (string) $record->getKey();
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Membership::query()
                ->where('contact_id', $record->getKey())
                ->where('status', 'active')
                ->orderBy('starts_on', 'desc')
                ->with('tier')
                ->first();
        }

        return $this->systemModelProjector->projectOne($contract, $cache[$key]);
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

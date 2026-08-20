<?php

namespace App\WidgetPrimitive;

use App\Models\Collection as CmsCollection;
use App\Models\CollectionItem;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Fund;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\NavigationMenu;
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
            if ($contract->requiredPermission !== null && ! auth()->user()?->can($contract->requiredPermission)) {
                $results[$i] = $contract->cardinality === DataContract::CARDINALITY_ONE
                    ? ['item' => null]
                    : ['items' => []];
                continue;
            }

            $results[$i] = match ($contract->source) {
                DataContract::SOURCE_PAGE_CONTEXT        => $this->pageContextProjector->project($contract, $context->currentPage()),
                DataContract::SOURCE_RECORD_CONTEXT      => $this->recordContextProjector->project($contract, $this->recordFromContext($context)),
                DataContract::SOURCE_SYSTEM_MODEL        => $this->resolveSystemModel($contract, $cache, $context),
                DataContract::SOURCE_WIDGET_CONTENT_TYPE => $this->resolveWidgetContentType($contract, $context, $cache, $fallback),
                DataContract::SOURCE_SERVICE             => $this->resolveService($contract),
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
                'event'           => $this->resolveEventOne($contract, $cache),
                'product'         => $this->resolveProductOne($contract, $cache),
                'membership'      => $this->resolveMembershipOne($contract, $cache, $context),
                'navigation_menu' => $this->resolveNavigationMenuOne($contract, $cache),
                'portal_member'   => $this->resolvePortalMemberOne($contract),
                default           => ['item' => null],
            };
        }

        return match ($contract->model) {
            'post'            => $this->resolvePost($contract, $cache),
            'event'           => $this->resolveEvent($contract, $cache),
            'product'         => $this->resolveProduct($contract, $cache),
            'note'            => $this->resolveNote($contract, $cache, $context),
            'donation'        => $this->resolveDonationList($contract, $cache, $context),
            'fund'            => $this->resolveFundList($contract, $cache),
            'membership_tier' => $this->resolveMembershipTierList($contract, $cache),
            default           => ['items' => []],
        };
    }

    /**
     * Resolve a list of active, non-archived Funds for donation designation.
     * The scope (is_active, not archived, name order) lives in the arm, not
     * the template — the widget only declares which fields it consumes.
     *
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveFundList(DataContract $contract, array &$cache): array
    {
        $key = 'fund:list';
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = Fund::query()
                ->where('is_active', true)
                ->where('is_archived', false)
                ->orderBy('name')
                ->get();
        }

        return $this->systemModelProjector->project($contract, $cache[$key]);
    }

    /**
     * Resolve a list of active, non-archived MembershipTiers in sort order,
     * for the portal signup tier picker. Scope lives in the arm.
     *
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveMembershipTierList(DataContract $contract, array &$cache): array
    {
        // Composition safety (contract surface 5 core-model caveat, session
        // 393): the membership_tiers table is plugin-owned schema, and the
        // seeded portal signup page consumes this arm on every composition —
        // on a memberships-absent install the read must degrade to an empty
        // list, not error. Route presence is the established plugin-presence
        // signal (the setup-checklist fund-row precedent).
        if (! \Illuminate\Support\Facades\Route::has('membership.checkout')) {
            return ['items' => []];
        }

        $key = 'membership_tier:list';
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = MembershipTier::query()
                ->where('is_active', true)
                ->where('is_archived', false)
                ->orderBy('sort_order')
                ->get();
        }

        return $this->systemModelProjector->project($contract, $cache[$key]);
    }

    /**
     * Resolve a single NavigationMenu by id, with its visible item tree
     * (three levels, sort-ordered, page relations loaded) attached for the
     * projector. Missing/invalid id or menu yields ['item' => null] — the
     * Nav template's existing render-nothing branch.
     *
     * @param  array<string, mixed>  $cache
     * @return array{item: array<string, mixed>|null}
     */
    private function resolveNavigationMenuOne(DataContract $contract, array &$cache): array
    {
        $id = (string) ($contract->filters['id'] ?? '');
        if ($id === '') {
            return ['item' => null];
        }

        $key = 'navigation_menu:one:' . $id;
        if (! array_key_exists($key, $cache)) {
            $menu = NavigationMenu::query()->find($id);
            if ($menu !== null) {
                $menu->setRelation('items', $menu->items()
                    ->where('is_visible', true)
                    ->whereNull('parent_id')
                    ->orderBy('sort_order')
                    ->with([
                        'page',
                        'children' => fn ($q) => $q->where('is_visible', true)->orderBy('sort_order')->with([
                            'page',
                            'children' => fn ($q2) => $q2->where('is_visible', true)->orderBy('sort_order')->with('page'),
                        ]),
                    ])
                    ->get());
            }
            $cache[$key] = $menu;
        }

        return $this->systemModelProjector->projectOne($contract, $cache[$key]);
    }

    /**
     * Resolve the authenticated portal member (PortalAccount + linked Contact).
     * Logged-out = ['item' => null] — exactly the render-nothing branch the
     * portal templates already take. PII-sensitive arm: only contract-declared
     * fields project (fail-closed), and the registrations relation loads only
     * when the contract declares `event_registrations`, so presence-only
     * widgets pay no extra query.
     *
     * @return array{item: array<string, mixed>|null}
     */
    private function resolvePortalMemberOne(DataContract $contract): array
    {
        $member = auth('portal')->user();
        if ($member === null) {
            return ['item' => null];
        }

        $member->loadMissing('contact');
        if (in_array('event_registrations', $contract->fields, true) && $member->contact !== null) {
            $member->contact->load(['eventRegistrations' => fn ($q) => $q->with('event')->orderByDesc('registered_at')]);
        }

        return $this->systemModelProjector->projectOne($contract, $member);
    }

    /**
     * Dispatch a SOURCE_SERVICE contract to its service-backed arm. Services
     * resolve lazily (per arm, not per resolver construction) so widgets that
     * never declare these contracts pay nothing.
     *
     * @return array<string, mixed>
     */
    private function resolveService(DataContract $contract): array
    {
        return match ($contract->model) {
            'setup_checklist' => $this->resolveSetupChecklist($contract),
            'scrub_counts'    => $this->resolveScrubCounts($contract),
            default           => $contract->cardinality === DataContract::CARDINALITY_ONE
                ? ['item' => null]
                : ['items' => []],
        };
    }

    /**
     * Setup-checklist dataset for the super-admin SetupChecklist widget. The
     * arm hard-gates on super-admin: anyone else gets ['item' => null], which
     * the template renders as nothing — its existing behavior.
     *
     * @return array{item: array<string, mixed>|null}
     */
    private function resolveSetupChecklist(DataContract $contract): array
    {
        if (! (auth()->user()?->isSuperAdmin() ?? false)) {
            return ['item' => null];
        }

        $service = app(\App\Services\Setup\SetupChecklist::class);

        return $this->projectServiceItem($contract, [
            'is_first_run' => (bool) $service->isFirstRun(),
            'items'        => array_map(fn (array $item) => [
                'title'         => (string) ($item['title'] ?? ''),
                'description'   => (string) ($item['description'] ?? ''),
                'message'       => (string) ($item['message'] ?? ''),
                'status'        => (string) ($item['status'] ?? ''),
                'category'      => (string) ($item['category'] ?? ''),
                'configure_url' => (string) ($item['configure_url'] ?? ''),
            ], $service->items()),
        ]);
    }

    /**
     * Scrub-data counts for the super-admin RandomDataGenerator widget. Same
     * hard super-admin gate as the setup checklist.
     *
     * @return array{item: array<string, mixed>|null}
     */
    private function resolveScrubCounts(DataContract $contract): array
    {
        if (! (auth()->user()?->isSuperAdmin() ?? false)) {
            return ['item' => null];
        }

        return $this->projectServiceItem($contract, [
            'counts' => array_map('intval', app(\App\Services\RandomDataGenerator::class)->scrubCounts()),
        ]);
    }

    /**
     * Fail-closed field filter for service-backed single-row DTOs — mirror of
     * SystemModelProjector::projectOne for non-model datasets.
     *
     * @param  array<string, mixed>  $full
     * @return array{item: array<string, mixed>}
     */
    private function projectServiceItem(DataContract $contract, array $full): array
    {
        $out = [];
        foreach ($contract->fields as $field) {
            $out[$field] = $full[$field] ?? '';
        }

        return ['item' => $out];
    }

    /**
     * Resolve a list of Notes attached to the ambient record. Permission gate
     * is centralized in resolve() via the contract's $requiredPermission. Ambient
     * gate: returns empty when the slot is not record-detail. Scoping: notes are
     * filtered by `notable_type` and `notable_id` derived from the ambient
     * record — the contract carries no per-instance scope.
     *
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveNote(DataContract $contract, array &$cache, SlotContext $context): array
    {
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
     * gate is centralized in resolve() via the contract's $requiredPermission.
     * Ambient gate: returns empty when the slot is not record-detail or the
     * ambient record is not a Contact. Status filter: excludes `pending`
     * (checkout-in-flight); includes active/cancelled/past_due history.
     *
     * @param  array<string, mixed>  $cache
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveDonationList(DataContract $contract, array &$cache, SlotContext $context): array
    {
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
     * withSum on event_registrations.quantity — one coordinated query, no N+1.
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
                ->with([
                    'media',
                    'landingPage',
                    'ticketTiers' => fn ($q) => $q->withSum(['registrations as registered_count' => fn ($r) => $r->whereIn('status', ['pending', 'registered', 'waitlisted', 'attended'])], 'quantity'),
                ])
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
            $query = Event::published()->with(['media', 'landingPage', 'tags']);

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
     * gate is centralized in resolve() via the contract's $requiredPermission.
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

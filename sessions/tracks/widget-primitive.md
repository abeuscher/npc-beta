# Track: Widget Primitive

The canonical planning + history doc for the Widget Primitive architectural arc. Premise and rationale live alongside in `widget-primitive-premise.md`.

This doc carries three things:

- **Status snapshot** — where the track is right now, what's next.
- **Phase Retrospectives** — compressed history of closed phases (sessions list, outcomes, key decisions, carry-forwards). Replaces the per-session paragraph stack that previously lived in `session-outlines.md`.
- **Forward plan** — the migration sketch (Phase 1–6 sequence and the Phase 5 sub-sequence), design decisions locked in, security posture, content shapes, known risks, forward hooks. Forward-looking detail accumulates here as future phases get scoped.

When a phase closes, its retrospective lands in this doc and its entry in the roadmap (`session-outlines.md`) collapses to a one-liner.

---

## Status snapshot

**Last update:** 2026-04-28 (Phase 5e closed, session 235 shipped centralized `requiredPermission` enforcement at the resolver dispatch boundary).

**Complete:** Phases 1, 2, 3 (3a–3d), 4 (4a–4h + follower), 5a, 5b, 5c, 5c.5, 5d, **5e**. Sessions 209–224, 227, 228, 229, 230, 231, 232, 234, 235.

**Active:** None — track is in steady state between phases. The DashboardConfig → DashboardView retrofit is **queued as session 236** — the cheap, named-ad-hoc follow-up that lifts the dashboard's per-role widget-composition model (`DashboardConfig`, session 215) into the unified View vocabulary by renaming both the model class and the table to mirror `RecordDetailView`'s shape.

**Remaining in track:** ~1–3 sessions.

- DashboardConfig → DashboardView retrofit (queued as 236)
- Phase 5d-4 — Recent Activity widget if pursued (1–2 sessions; aggregates across multiple system models — its own design surface and a stress-test of 5e's `requiredPermission` grammar against a multi-model widget)
- Phase 6 — page-builder convergence (0–1 standalone sessions; partially landed via the Vue reuse in 215, deepened by the same reuse for record-detail in 230)

**Named risk for the Phase 5 arc** ("low probability the contract layer cannot coexist with Filament's Livewire model") was answered in 5b — it does coexist.

---

## Phase Retrospectives

Compressed history. Per-session detail lives in the matching `sessions/(archived/)NNN. … — Log.md` files.

### Phase 1 — Data contract prototype (session 209)

Two real widgets (BlogListing, EventsListing) had their `DataContract` declarations written by hand and a resolver built that turned contracts into DTOs. Verdict: **proceed with refinements.** The contract language was expressive enough for the test cases without becoming a second ORM.

### Contract Refinements (session 210)

Projector extraction, fallback semantics into the resolver, `PageContextTokens` singleton with per-page memoization, richtext-consumer pattern documented. Mid-session pivot: the **source-as-capability** simplification — `SOURCE_PAGE_CONTEXT` contracts declare no `fields` and the resolver returns the full bounded `PageContextTokens::TOKENS` map. The other two sources keep per-field fail-closed discipline. This is the load-bearing decision the rest of the track depends on.

### Phase 2 — Slot taxonomy (session 211)

`Slot` abstract + `SlotRegistry` singleton; three slot declarations (`PageBuilderCanvasSlot` live, `DashboardGridSlot` and `RecordDetailSidebarSlot` declaration-only with `RuntimeException` bodies pending later phases); `WidgetDefinition::allowedSlots()` default method (`['page_builder_canvas']`); `WidgetRenderer` retrofitted to construct ambient context through the canvas slot. Byte-equivalent output preserved. 15 new unit tests across 6 files.

### Phase 3 — Dashboard grid (sessions 212–215, four sub-sessions)

- **3a (212):** `DashboardGridSlot::ambientContext()` opened. `WidgetRenderer::render()` gained a `$slotHandle` parameter (default `page_builder_canvas`, byte-equivalent for existing callers; `dashboard_grid` branch skips tokenPage and token-substitution). `DashboardSlotGridWidget` Filament widget mounts on the admin dashboard. Surfaced and fixed: a Livewire morph / Alpine race around per-lib asset loading, resolved by extracting `WidgetAssetResolver` (singleton) and emitting lib bundles from a `panels::head.end` render hook.
- **3b (213):** `App\WidgetPrimitive\Source` final class with six well-known constants (`HUMAN`, `DEMO`, `IMPORT`, `GOOGLE_DOCS`, `LLM_SYNTHESIS`, `STRIPE_WEBHOOK`); `HasSourcePolicy` trait (instance-based `acceptsSource`, fail-closed when `ACCEPTED_SOURCES` constant absent, universal `HUMAN` pass) applied to `Page`, `Contact`, `Donation`; `DataSink` singleton with three guards. Sharpest security win: `Donation::ACCEPTED_SOURCES` explicitly excludes `Source::DEMO`.
- **3c (214):** Three dashboard-native widgets shipped (Memos, Quick Actions, This Week's Events). `publicSurface: bool` field added to `SlotContext` — admin slots pass `false`, which drops `is_public` filtering inside `ContractResolver::resolveWidgetContentType()`. This is what unlocks admin-only collections (memos) on the dashboard without public exposure leaking.
- **3d (215):** `dashboard_configs` table as polymorphic owner of `page_widgets` (honors the polymorphic-owner discipline — no parallel `dashboard_widgets` table). The Vue page builder is reused in a new `dashboard` mode — first concrete step of Phase 6 (page-builder convergence) arriving naturally rather than as a later rewrite.

### Phase 4 — Retrofit existing widgets onto contracts (sessions 216–224, nine sub-sessions including the follower)

All ~16 page-builder widgets that had a data path now declare `dataContract($config)`; templates read from `$widgetData['items']` (list) or `$widgetData['item']` (single-row).

- **4a (216) BlogListing audit-style retrofit.** Established the test-lock pattern (fail-closed field whitelist + resolver-is-only-path query-count pin).
- **4b (217) EventsListing.** Retired `PageContext::upcomingEvents()`. Locked the **projector field-naming convention** — source-column name carries the canonical timestamp (`starts_at` ISO), `_label` suffix the presentation companion (since superseded by 226's concept-named convention).
- **4c (218) Carousel.** First `SOURCE_WIDGET_CONTENT_TYPE` retrofit. **Established system rule:** contract-locked widgets do not declare `collections()`. Demo data flows only via `DemoSeeder`-seeded real Collections.
- **4d (219) BarChart.** First widget where `dataContract()`'s `fields` list is dynamic per-instance. **Stale-seeder discipline gap surfaced** — `WidgetTypeSeeder` must be re-run after `collections()` removal; carry-forward rule established. Production deploy note carried.
- **4e (220) BoardMembers + LogoGarden.** Two SWCT retrofits in one session. After 220, `WidgetRenderer::render()`'s legacy slot-strip loop iterates 0 times for every widget.
- **Follower (221) — Query settings in the contract layer.** Five-knob (`limit`, `order_by`, `direction`, `include_tags`, `exclude_tags`) panel flows through the contract layer for all eight list-shaped widgets uniformly. `App\WidgetPrimitive\QuerySettings` value object on the contract. Vue panel rebuilt per the no-collapse rule. **Sharpest finding:** `cmsTags` references in `WidgetDataResolver::resolveCustom` had been silently broken since session 040 — `include_tags`/`exclude_tags` was a no-op for ~14 months.
- **4f (222) BlogPager.** First `SOURCE_SYSTEM_MODEL` retrofit on Page-as-post. Retired `PageContext::posts()`. Surfaced and fixed a pre-existing token-namespacing bug — BlogPager's prev/next links were displaying host post metadata instead of neighbor metadata; tokens migrated to `{{item.foo}}` namespace.
- **4g (223) EventDescription + EventRegistration.** **First non-list-shaped contract.** Locked the single-row DTO shape `['item' => row | null]`, the `cardinality: 'one' | 'many'` field on `DataContract`, and the **aggregate-derived field via `withCount` baked into the resolver** pattern (`is_at_capacity`). Retired `PageContext::event()`.
- **4h (224) ProductCarousel + ProductDisplay.** Brand-new `'product'` arm in both `ContractResolver` and `SystemModelProjector`. **First nested DTO projection** (Product.prices). The entire `WidgetDataResolver` shell retired. Out-of-scope cleanup: legacy `TransactionSeeder` deleted entirely. **Closes Phase 4.**

After Phase 4: every widget in `app/Widgets/*` with a data path declares a contract; `WidgetDataResolver` is gone; `PageContext` retains only `currentPage`, `currentUser`, and `form()`. The Forms widget retrofit (which would retire `form()`) has no scheduled session.

### Phase 5a — Typed ambient context primitive (session 227)

Abstract `App\WidgetPrimitive\AmbientContext` (sealed by convention) with three final subtypes (`PageAmbientContext` carrying `?Page`, `DashboardAmbientContext` empty, `RecordDetailAmbientContext` empty — payload landed in 5b). `SlotContext` constructor refactored from `(PageContext, ?Page, bool)` to `(AmbientContext, bool $publicSurface)`; `currentPage()` survives as a delegate that asks the ambient. 14 test files rippled for the constructor swap; 4 new unit test files. **Architectural decision locked in during 227 close-gate:** the **"a+" View framing** — `View` is the shared widget-composition primitive expressed as the `App\WidgetPrimitive\IsView` PHP interface; `DashboardView` and `RecordDetailView` adopt the vocabulary natively; CMS `Page` keeps its own table and full public-facing surface and implements `IsView` as a small adapter only if/when "walk every widget-mounting surface" becomes load-bearing (could be never). No unified `views` table, no Page refactor.

### Phase 5b — Filament mount + record-detail ambient wiring + IsView interface + placeholder widget (session 228)

`RecordDetailAmbientContext` payload (`Model $record`) opened; `RecordDetailSidebarSlot::ambientContext($record)` body opened; `SOURCE_RECORD_CONTEXT` + `RecordContextProjector` + `RecordContextTokens` (mirror of the page-context trio, source-as-capability); `IsView` PHP interface declared; first concrete `RecordDetailView` (hardcoded array — table-backing in 5c); Filament hosting on `EditContact` via `RecordDetailViewWidget` (mirror of `DashboardSlotGridWidget`); placeholder widget at `app/Widgets/RecordDetailPlaceholder/` with `allowedSlots: ['record_detail_sidebar']` and a `SOURCE_RECORD_CONTEXT` contract. Visible verification: a Contact's edit page rendered "Record detail sidebar — Contact #N" through the full pipeline. Fast Pest 1503/0. **The named Phase 5 risk got answered: the contract layer coexists with Filament's Livewire model.**

### Phase 5c / 5c.5 — `record_detail_views` table + IsView registry + sub-nav rendering primitive + Templates/Themes refactor + admin authoring UI (sessions 229, 230)

`record_detail_views` table (uuid PK, `handle`, `record_type` FQCN, `label`, `sort_order`, `layout_config` jsonb, unique on `(record_type, handle)`); `RecordDetailView` lifted from 5b's plain class to an Eloquent model implementing `IsView`, with polymorphic widget ownership via `page_widgets.owner_type='App\WidgetPrimitive\Views\RecordDetailView'` (mirror of `DashboardConfig`); `ViewRegistry` singleton (`forRecordType`, `findByHandle` — thin Eloquent query wrapper, table is canonical); `HasRecordDetailSubNavigation` trait with four hooks (`subNavigationEntryPage`, `recordDetailViewSubPageClass`, `additionalSubNavigationPages`, plus the `getSubNavigation` body) ordering entries `[entry] + [Views] + [additional pages]` and filtering each through `canAccess(['record' => $this->record])`; abstract `RecordDetailViewPage` for future widget-grid sub-pages (concrete subclass deferred to 5d+ when first concrete widget arrives); `RecordDetailViewWidget` rewired to read from registry with byte-equivalent fallback for the EditContact footer mount (also `$isDiscovered = false` to keep the auto-discovered widget off the dashboard); `RecordDetailViewSeeder` seeds `contact_overview` (with placeholder widget attached) plus chrome anchors `page_template_header` / `page_template_footer`. Templates/Themes refactor folded in: `EditPageTemplate` restructured to "Label and Colors" entry (Name + Description half-width stacked above six color pickers full-width), `EditPageTemplateChrome` mounts the existing page-builder against the parent template's `header_page_id` / `footer_page_id` per the View handle (incidentally fixing a long-standing "Header and Footer reference each other on save" bug because sub-nav mounts one chrome page-builder per request rather than both simultaneously), `EditPageTemplateScss` extracted as a custom `Page` with the SCSS save/build flow. Sub-nav order on every page in the cluster: `Label and Colors → Header → Footer → SCSS`. Page-builder preview-link patched to route to `/` when `type='system' && slug` starts with `_` (chrome pages). Fast Pest 1524/0. **Key load-bearing decisions made in flight:** (a) the prompt's "Header/Footer become empty Views" framing was clarified to "Views are page-builder-mount anchors, page-builder mounts inside" per the user's chrome-bug motivation; (b) PHP's static-as-non-static collision forced `$detailView` / `$viewHandle` property renames vs the prompt's `$view`; (c) `canAccess` filtering was added to the trait during verification when chrome-permission tests caught the gap. Per-record-type token expansions on `RecordContextTokens::TOKENS` and the `PageContextTokens` namespace migration both remain deferred carry-forwards.

**5c.5 follow-up (session 230)** added the admin authoring UI on top of the 5c primitive. `RecordDetailViewBuilderApiController` mirrors `DashboardBuilderApiController` (manage_record_detail_views permission, IDOR scoping to the View, slot-allowedSlots check on create, appearance whitelist `['background', 'text']`) and additionally lands layout CRUD methods (storeLayout / updateLayout / destroyLayout) plus a tree-with-layouts buildTree mirroring `PageBuilderApiController`'s shape — record-detail allows column layouts (`column_stackable: true`), unlike the dashboard. Routes file owner-scoped under `views/{view}/...` so IDOR is automatic at the route binding level. Vue page-builder gained `record_detail` editor mode: `EditorMode` extended to `'page' | 'dashboard' | 'record_detail'`; `viewLabel` + `recordTypeLabel` refs added to the store; `InspectorPanel` refactored from `isDashboard` to `isPageMode` semantics so Presets and Spacing hide in any non-page mode; `EditorToolbar` shows `View: {label} ({record_type})`; `App.vue` hides Save-as-Template footer in any non-page mode. `RecordDetailViewBuilder` Livewire shell mirrors `DashboardBuilder` (403 fail-closed, slot-filtered widget types, api_base_url == api_lookup_url scoped to the View). `RecordDetailViewResource` (Tools group, gated, list/create/edit/delete) with hardcoded `getRecordTypeOptions(): [Contact::class => 'Contact']` (no dynamic discovery; auditable surface) and `getEloquentQuery()` that excludes Template-bound chrome rows (`page_template_*`). Edit page uses a custom Blade adapted from Filament's `edit-record.blade.php` — renders the metadata form via `<x-filament-panels::form>` then mounts the Livewire builder beneath. `RecordDetailView` model gained a `static::booted()` `deleting` callback that cascades both polymorphic `page_widgets` (per-row delete so Spatie media cleans up) and `page_layouts`. `manage_record_detail_views` permission seeded; help doc stub at `resources/docs/record-detail-views.md` registered via `help:sync` (3 routes). 19 new test cases (11 controller + 5 Resource + 2 Livewire bootstrap + 1 cascade); fast Pest 1543/0 (+19); `npm run build` clean; no `build:public` required (no widget assets touched). **Forward gap surfaced during manual testing:** EditContact's `recordDetailViewSubPageClass()` returns null and `RecordDetailViewWidget::resolveView()` falls back to `forRecordType()->first()`, so when a user creates two Views bound to Contact only the first renders. The architecture supports multi-View on Contact (the Templates/Themes refactor in 5c demonstrates the working case); the wiring lands in 5d-1 alongside the first concrete widget. **Decisions surfaced in flight:** (a) URL parameter named `{view}` rather than `{recordDetailView}` to match the prompt's `views/{viewId}` API surface — implicit Laravel route model binding via the controller's `RecordDetailView $view` type-hint resolves cleanly without a global `Route::bind` side-effect; (b) the Resource form-level uniqueness rule uses Filament's `unique(...)` with `modifyRuleUsing` closure to scope the where clause to the current `record_type`, with `ignoreRecord: true` for edit; (c) layout cascade on View deletion runs *after* the per-row PageWidget cascade rather than relying on the `page_widgets.layout_id` FK cascade — ensures Spatie media cleanup runs on every widget regardless of whether it's root or layout-nested. Phase 5d-1 (Recent Notes) is the next phase boundary; admin UI for chrome Views, the placeholder-widget cleanup pass, the DashboardConfig→DashboardView retrofit, and the Themes/Templates nav merge all remain deferred.

### Phase 5d — Concrete record-detail widgets (sessions 231, 232, 234)

Three concrete consumers of the record-detail Slot primitive landed in succession, each adding a new arm to `ContractResolver::resolveSystemModel()`, a `projectXxx()` method on `SystemModelProjector`, a server-rendered Blade widget, and a seeder attachment to the `contact_overview` View. **5d-1 (231)** Recent Notes — first concrete consumer; cardinality-many. Iteration-2 added a sub-nav threshold change so the seeded primary View doesn't trigger sub-nav alone, plus a `primaryHandles()` registry on `RecordDetailViewResource` that protects seeded primaries from deletion. **5d-2 (232)** Membership Status — first single-row record-detail contract (`cardinality: 'one'`); ambient gate narrows to Contact records; `RecordDetailViewSeeder` refactored from count-based early-return to per-handle `attachWidgetIfMissing()` for idempotent multi-widget seeding. Close-gate pivot spawned the Financial Data Origin track and pushed 5d-3 to session 234. **5d-3 (234)** Recent Donations — first read-side consumer of session 233's `source` column via a `donation_origin` projector field (Stripe / Imported / Manual); Source badge column added to `/admin/donations` for verification ergonomics. The phase added `recent_notes` / `membership_status` / `recent_donations` to the registry (widget_types 34 → 37); fast Pest grew from 1543 (post-5c.5) to 1641 (post-5d-3) — net +98 across the phase. The forcing function for Phase 5e: each arm gained a structurally-identical inline `auth()->user()?->can($permission)` gate; three evidence points by 234 close confirmed clean compression was possible. **Deferred at phase close:** Membership history widget; projector-side label assembly for Membership Status; `show_amount` config knob; placeholder widget removal; DashboardConfig→DashboardView retrofit; Themes/Templates nav merge; importer→widget status mismatch fix.

### Phase 5e — Permissions in the contract layer (session 235)

`DataContract` gained a `?string $requiredPermission` constructor parameter declared between `$cardinality` and `$formatHints`; `ContractResolver::resolve()` gained a top-of-loop centralized gate that checks `auth()->user()?->can($contract->requiredPermission)` before source dispatch and returns the cardinality-correct empty shape (`['item' => null]` or `['items' => []]`) on deny. The three inline `Gate::denies('view_X')` lines were lifted from `resolveNote` / `resolveMembershipOne` / `resolveDonationList` — their docblocks now point to the centralized enforcement. The three concrete record-detail widget definitions declare `requiredPermission: 'view_note'` / `'view_membership'` / `'view_donation'` on their `DataContract` constructor calls. Behavior is byte-equivalent at every observable point: existing per-arm gate tests stay green via the centralized path; a new `ContractResolverPermissionGateTest` covers the dispatch-level mechanism directly with five cases (MANY/ONE deny shapes, granted-user dispatch, null-gate skip, guest fail-closed). Centralization is source-agnostic by construction — the gate runs for all four `SOURCE_*` types, so future contracts on PageContext / WidgetContentType / RecordContext inherit the mechanism without further work. **Decisions locked in:** `null` is the *declared* "no permission gate" state (consistency with other optional `DataContract` fields); single-permission boolean checks only — instance-bound `Gate::denies('view', $model)` stays a forward hook for a future arm; ambient-type gates (`! $record instanceof Contact`) stay per-arm as a separate axis. Fast Pest 1641 → 1649 (+8: 5 new gate tests + 3 definition assertion extensions). Closes the per-arm-gate question cleanly; no carry-forward into the next track session.

---

## Stance

- **This is a 2.0 track, not a v1 feature.** The existing codebase ships to beta and 1.0 on its current architecture. The widget primitive transition happens after 1.0.
- **Pragmatic, not purist.** Filament stays. It is excellent at CRUD — resources, forms, tables, relation managers, policy gates, bulk actions. Rebuilding those abstractions has no product payoff and enormous cost.
- **The widget primitive is for surfaces where composition is the point.** Dashboard grid, record-detail sidebar, public page builder, eventually email blocks. Not Filament resource edit pages.
- **Evolution, not rewrite.** Filament continues where it's good. A slot layer is added alongside it. Existing CRM widgets in `app/Widgets/*` become the contract-bound tile primitive for new slot surfaces.
- **No framework preference.** Vue stays where interactivity demands it (page builder, theme editor). Livewire/Blade stays where Filament's abstractions are load-bearing. The admin stack stays mixed, which is fine.

## What this is optimizing for

1. **Solo-developer maintainability over years.** Bounded reasoning — the ability to come back to one part of the system in six months and understand it without having to remember how it touches everything else.
2. **Faster iteration after release.** New features become "declare a contract, write a widget" rather than "trace through every layer of the app." Atomic construction as a maintenance strategy.
3. **Security posture.** Fail-closed reads. Static auditability. Explicit declaration of what every surface touches.
4. **Composability.** The same data surface rendered in multiple slots without the host knowing. A "recent donations" tile appears on the dashboard, in a contact sidebar, and in an email — one widget, three mount points.

## Preparation — inside the v1 budget

Cheap, non-blocking alignment moves. Done inside the ~40 sessions between beta-1 and 1.0, not before beta-1.

### The discipline moves (essentially zero cost)

Applied as rules during other sessions, not as discrete deliverables:

- **Polymorphic owner discipline.** Anything widget-shaped uses the existing `page_widgets` / `page_layouts` tables with new owner types. No parallel tables for "dashboard widgets" or "email blocks" if that work happens in v1.
- **Reference by handle, not UUID.** Widget types, collection handles, template keys. Handles survive schema rewrites.
- **Keep `appearance_config` slot-neutral.** Session 207 established the pattern by moving `full_width` to `layout_config` because it's canvas-specific. Preserve the split: appearance = "how it looks," layout_config = "how this slot arranges it."
- **No private extension paths.** If a feature is tempting to build as a Filament-specific JSON blob, reconsider — store it widget-shaped if it's widget-shaped.

### The `dataFields()` declaration pass (1–2 sessions)

Add a `dataFields(): array` method to `WidgetDefinition` and populate it for all ~20 widgets in `app/Widgets/*`. No resolver consumes it yet. The artifact is the point — when the 2.0 resolver lands, widget data requirements are already declared, not reconstructed from template archaeology.

### Custom fields tightening (3–5 sessions, optional)

Make custom fields typed and addressable by handle rather than opaque JSONB blobs. Has independent v1 value for users. Roughly a wash as pure 2.0 prep — either pay it in v1 or pay it in v2 plus a data migration.

### Budget impact

Cheap package (discipline + `dataFields`) is 1–2 sessions = 2–4% of the beta-to-1.0 budget. Saves an estimated 5–11 sessions at 2.0 transition time. Clear win.

## The 2.0 transition — rough sequence

Executed after 1.0 ships. Each phase gates the next; Phase 1 is load-bearing for the decision to continue at all.

### Phase 1 — Data contract prototype (1–2 sessions)

Pick two real widgets (the premise doc suggests "recent donations list" and "donor lifetime value tile" — these hold up). Write their `DataContract` declarations by hand. Build the resolver that turns contracts into DTOs. No UI yet. This is the session that tells you whether the premise survives contact with reality. **If the contract language isn't expressive enough for real cases without becoming a second ORM, stop here.**

### Phase 2 — Slot taxonomy (1–2 sessions)

Declare slots formally. For each: ambient context, layout constraints, config surface. Minimum set for v2.0: dashboard grid, record-detail sidebar, public page-builder canvas (already exists — retrofit under the new abstraction).

### Phase 3 — First new slot: dashboard grid (3–5 sessions)

Admin dashboard as the inaugural non-page-builder slot. Rendered inside Filament's existing admin chrome. Two or three stock widgets at launch. Proves the primitive works outside the page builder.

### Phase 4 — Retrofit existing widgets onto contracts (5–8 sessions)

~20 widgets in `app/Widgets/*` migrated to declared contracts. Templates stop walking relationships. The resolver becomes the only data path. Tests updated. Also within this phase: migrate existing `collection_items` rows under widget-declared content type ownership per the Content shapes decision below, and retire the admin UI for creating arbitrary Collections.

### Phase 5 — Record-detail slot + ambient context refactor (4–6 sessions)

`PageContext` generalizes to `SlotContext` with subtypes (page, dashboard, record-detail). Record-detail sidebar becomes a slot. Widgets that need "the current contact" get it from the slot's ambient context, not from controller plumbing.

Phase 5 also lands the **View** primitive (see "Views as the shared widget-composition primitive" decision below) and absorbs the previously-parallel **Templates/Themes record sub-navigation refactor** (originally a separate stub at `session-outlines.md` line 457 area) — the record-detail sub-nav and the Templates/Themes sub-nav are the same architectural pattern with different ambient contexts, so building two would be wrong. Sub-nav rendering lands in 5c as a Phase-5 primitive that the Templates/Themes refactor consumes.

Sequence as of 2026-04-28:

- **5a** (session 227, complete): typed ambient context primitive — `AmbientContext` abstract + three subtypes; `SlotContext` carries a typed ambient instead of a `(PageContext, ?Page)` pair.
- **5b** (session 228, complete): Filament mount point for record-detail + `RecordDetailSidebarSlot::ambientContext($record)` body opens + `SOURCE_RECORD_CONTEXT` source/projector/tokens + `IsView` interface + first concrete `RecordDetailView` (hardcoded array, table-backing in 5c) + placeholder widget that confirms the ambient flows on `EditContact`. The named risk for the Phase 5 arc — "low probability the contract layer cannot coexist with Filament's Livewire model" — gets answered here.
- **5c / 5c.5** (sessions 229, 230, complete): `record_detail_views` table + `IsView` registry + sub-nav rendering primitive + admin UI for authoring per-record-type View sets. Templates/Themes record sub-navigation refactor folds in as a consumer of the same primitive.
- **5d** (sessions 231, 232, 234, complete): three concrete record-detail widgets — Recent Notes, Membership Status, Recent Donations — each contributing a per-arm permission gate that 5e centralized.
- **5e** (session 235, complete): permissions in the contract layer — `requiredPermission` field on `DataContract` enforced at the resolver dispatch boundary; three per-arm gates lifted to centralized enforcement; mechanism inherits source-agnostically across all four `SOURCE_*` types.
- **DashboardConfig → DashboardView retrofit** (queued as session 236): cheap, named-ad-hoc follow-up that lifts `DashboardConfig` (session 215) into the unified View vocabulary by renaming both the model class (to `App\WidgetPrimitive\Views\DashboardView`) and the table (to `dashboard_views`), implementing the `IsView` interface that has carried its name in the docblock since Phase 5b. Small migration (table rename + polymorphic `owner_type` backfill on `page_widgets`); behavior byte-equivalent end-to-end.
- **5d-4 if pursued**: Recent Activity widget aggregating across multiple system models; not currently queued.

### Phase 6 — Page-builder convergence (1–2 sessions)

Ensure the page builder consumes the same primitive. Should be mostly rename/refactor — the page builder is already widget-shaped; the data path becomes contract-bound like every other slot.

### Pending paradigm questions (mid-arc, no committed session yet)

Architectural gaps the arc has been silent on. The remaining one will need addressing within the 5d+ window when concrete record-detail widgets force the issue, but it does not block any committed session and has no session number yet. Named here so it's findable and a reader walking in cold sees it in one place.

- **Widget help system integration.** The CRM has a help system (`resources/docs/{handle}.md`, search, related articles) but widgets can't currently express help docs that surface in it. A widget should be able to declare a help article that rolls up into the help for the View it's mounted in (or for the parent record-detail page, or for the dashboard). Same declarative-atom shape as data contracts — each widget declares; the surface aggregates. Open sub-questions: where does widget help live (per-widget folder mirrors session 172 colocation; or `resources/docs/widgets/{handle}.md`), whether the help registers as a standalone article or only rolls up under the parent View, and whether Views themselves carry help docs or are always the union of their widgets' help. **Forcing function:** any concrete record-detail widget benefits from this; with three concrete widgets (Recent Notes, Membership Status, Recent Donations) now shipped, the rollup gap is visible — the natural moment to lift it is when the next record-detail widget arrives, or the user surfaces the gap directly.

### Deferred

- **Email template block slot.** Not before 2.0 is shipped and stable.
- **Form field slot.** Custom field types as widget instances inside constituent forms. Stretch.
- **Write contracts.** The mutation-side analogue of data contracts. Writes continue on Filament's existing paths for 2.0.
- **Public extension API.** Documenting "how to write your own widget" for third parties. Not before the contract has been dogfooded internally for at least one full development cycle.

### Rough transition budget

Phases 1–6 ≈ 20–30 sessions. Can be interleaved with feature work after 1.0 ships, not a single-track blocking initiative.

## Design decisions locked in

- **Reading abstraction first.** The contract layer guards reads. Writes continue through Filament's existing paths until a separate mutation-contract story is built.
- **One resolver, hardened.** Not fifty consumer-side checks. Concentrated enforcement at a single boundary. This is the GNAP posture: one authorization server, not distributed trust.
- **Contracts versioned from day one.** Every contract declares its version. Old instances know what version they resolved against. No untracked drift.
- **DTOs are narrow and short-lived.** Each render cycle produces a fresh, minimal payload scoped to the contract. No long-lived object references handed to widgets.
- **Batching designed in.** Resolver accepts a list of contracts. Twelve widgets on a page = one coordinated resolution, not twelve cascades.
- **Structured, typed declarations.** No stringly-typed scopes. Contracts are typed PHP objects. The declaration surface is a first-class versioned artifact, reviewed like API.
- **Capability grain varies by source.** Fail-closed per-field discipline applies where the data is sensitive and the relationships are walkable. `SOURCE_SYSTEM_MODEL` and `SOURCE_WIDGET_CONTENT_TYPE` keep per-field declaration — widgets must state exactly which columns or content-type fields they read. `SOURCE_PAGE_CONTEXT` treats the source itself as the capability: contracts for this source declare no `fields`, and the resolver returns the full `PageContextTokens::TOKENS` map. The token set is a small, bounded artifact of public page metadata (title, date, author, excerpt, starts_at, location — plus whatever additive tokens land over time). Adding a token is a grep-visible edit to `PageContextTokens`, reviewed there, and made available to every richtext consumer simultaneously. No per-widget union declaration, no drift between widgets, no separate `SOURCE_*` constant for the richtext case. Decided in session 210 after the initial Phase 1 findings flagged the richtext-consumer case — the right read was that per-field granularity had never been meaningful for a six-scalar public-metadata set.
- **Views as the shared widget-composition primitive — vocabulary + interface, not a unified table (the "a+" decision).** A View is "a configured collection of widget instances arranged in a layout, scoped to a context type." Three concrete kinds at v1: CMS `Page` (slug-routed, public, has its own publishing/SEO surface), `DashboardView` (per-role, admin-only), `RecordDetailView` (per-record-type, admin-only, ambient-bound to a specific record at render time). Each implements the `App\WidgetPrimitive\IsView` interface declaring the widget-composition contract (handle, slot binding, widget set, layout config) — code that walks "every widget-mounting surface" calls the interface; surface-specific concerns stay on the surface. **No unified `views` table, no Page refactor.** Page keeps its own table (its public-page surface — slug, SEO, publishing, type, hierarchy, custom fields, blog/post discrimination — is too heavy to merge into a shared row, and would either bloat the row with always-null fields or split Page across two tables and double the public-render cost). DashboardView and RecordDetailView speak the View vocabulary natively from their introduction; Page implements the interface as a small adapter when "walk every widget surface" becomes load-bearing (could be never; see Page-as-View elective hook below). The marketplace's extensibility surface is **widgets + slots + sources + ambient contexts**, not Views — third-party widget developers contract against those four primitives, not against any specific View kind, and the marketplace works whether Views are unified or sibling. Decided in session 227 close, formalized at the start of session 228 (Phase 5b). Sources are a closed set of well-known string constants on `App\WidgetPrimitive\Source` (`HUMAN`, `DEMO`, `IMPORT`, `GOOGLE_DOCS`, `LLM_SYNTHESIS`, `STRIPE_WEBHOOK`) — adding one is a grep-visible boundary edit. Every write target declares its policy model-locally via the `HasSourcePolicy` trait with an `ACCEPTED_SOURCES` constant; `Source::HUMAN` is a universal pass (Filament forms keep writing through Eloquent unchanged). `Collection` overrides the trait's default to read per-row `accepted_sources`, and `CollectionItem` delegates to its parent collection — collections default to `["human"]` and the admin widens by explicit consent at creation. Fail-closed at every target: unknown source, missing trait, or rejected source all throw. No central registry — locality of policy-to-model is the point, and "who reads `Donation`?" remains a `grep Donation` against declared `ACCEPTED_SOURCES`. Scope is boundary, not field-level mutation authorization (that is the 2.0 write-contract story). Decided and landed in session 213.

## Security posture shift

What the contract layer buys:

- **Fail-closed reads.** A widget that forgets to declare a field simply doesn't render that field. Missing data is visible and testable; leaked data is silent. The dominant failure mode shifts from "data leaks silently" to "feature incomplete."
- **Static auditability.** "Who reads `contacts.ssn`?" is a grep against declared contracts, not a runtime instrumentation exercise. Compliance reviews become tractable.
- **Scope-narrowing is free.** Drop a field from a user's permission scope; the resolver stops populating it across every widget simultaneously. No consumer-side audit.
- **Contract diffs as security artifacts.** Widget PR diffs tell you exactly what the widget's data access changed.

What it does not buy:

- **Writes.** Separate concern. Filament's mass-assignment + policy story still governs mutations.
- **IDOR.** Resolver must be context-ownership-aware ("does this user actually own this contact context?"). Doesn't fall out of the contract alone.
- **Classical web vulns.** Injection, timing, logging leaks — all orthogonal.
- **Resolver bugs.** One hardened thing is better than fifty remembered checks, but bugs in the resolver have universal blast radius. The resolver itself must actually be hardened.

## The Filament question, resolved

Filament stays for CRUD. The pragmatic version is the decision. Specifically:

- Filament Resources, Pages, forms, tables, relation managers, policies — unchanged.
- The widget primitive and its slots sit **alongside** Filament, not inside or over it.
- New slot surfaces (dashboard, record-detail sidebar) are hosted inside Filament's admin chrome via lightweight mount points but consume the widget primitive, not Filament's own widget/form/table abstractions.
- The only scenario that re-opens the Filament question: if the Phase 1 prototype shows the contract layer cannot coexist with Filament's Livewire model. Low probability, worth naming.

## Content shapes — how users get new content types

Complementary to the Filament decision, resolved during scoping conversations for session 209. The widget primitive implicitly retires today's half-built "user creates an arbitrary Collection with whatever fields" capability. What replaces it is a three-way carve-up:

- **Widget-declared content types (the default).** Every widget that consumes collection-shaped content declares the content type it owns. Installing a widget registers its content type. Users populate items within that typed shape. Binding is by construction — no runtime validation step, no field-mapping UI, no schema drift.
- **Custom fields on system models (the augmentation path).** Data that augments existing entities — additional fields on Contact, Event, Organization — goes through the existing `custom_field_defs` / `custom_fields` pattern, extended outward from today's Contact-only implementation.
- **LLM-assisted widget authoring (the escape hatch).** Genuinely custom content shapes that no stock widget declares and no system-model augmentation fits: user describes the shape to an LLM, gets a scaffolded widget, installs it. The escape hatch is itself a widget — the architecture stays uniform. On-brand with the LLM-assisted data-prep path already on the roadmap.

The admin UI for creating arbitrary Collections is a half-built feature that was never delivered as a full self-service capability. Retiring it costs nothing the user could have done anyway. The `collections` / `collection_items` tables stay; their ownership migrates to widget-declared content types during Phase 4.

### Revenue model alignment

The three paths map onto three revenue surfaces. Stock widgets are product value. Custom widgets for paying clients are consulting revenue, contributable back to core without licensing friction because widgets are just code implementing an open primitive. LLM-assisted self-service handles the long tail at zero marginal cost. The primitive is the pricing surface, not the individual widgets — customization flows out to the broader audience without contractual or licensing entanglement.

## Known risks and open questions

Carried from the premise document and scoping conversations, worth naming without solving:

- **Contract expressiveness.** Can the declaration language describe nested relationships, aggregates, computed fields, time-series without becoming a second ORM? Phase 1 answers this or kills the project.
- **Configuration UI.** Admin needs to configure widgets in slots without writing code. Second-hardest design problem after the contract itself.
- **Asset scoping.** Blade does not scope CSS the way Vue SFCs do. Naming convention + build-step enforcement. Existing widget SCSS discipline already approximates this.
- **Upgrade safety.** When a widget contract changes between versions, what happens to configured instances? Versioning is the answer; the operational details aren't worked out.
- **Performance budget.** Twelve-widget dashboards cannot fire twelve query cascades. Batching has to be real from day one, not added later.

## Forward hooks — elective, not required

Options the architecture makes available but that no phase currently commits to. Named so the phases downstream know they exist and can reach for them if a concrete pain surfaces.

- **Appearance as a shared contract.** The typed-contract shape currently describes widget data only. `WidgetDefinition::defaultAppearanceConfig()` is already an informal declaration — a map of "which appearance fields this widget honors, with these defaults." Formalizing it into a versioned `AppearanceContract` inherited from the base class (overridable per widget) would give appearance the same auditability, slot-level enforcement, and feature-gating story the data contract has. "Which widgets honor `border_radius`?" becomes a grep across declared contracts. Phase 2 slots could declare "this slot forbids full-width" or "this slot overrides `background.color` from the grid theme" and enforce against the contract. Does not buy security (appearance isn't sensitive) or performance (`AppearanceStyleComposer` is already cheap). The right moment to formalize is when slot taxonomy (Phase 2) surfaces a slot-level appearance constraint the informal declaration can't express. Until then: elective.

- **Page-as-View — the elective bottom of the View stack.** The "a+" decision (locked in at the start of Phase 5b) keeps `Page` as its own table with its full public-facing surface intact, while DashboardView and RecordDetailView speak the View vocabulary natively. Page implements the `IsView` interface as a small adapter only when something forces it — e.g., when "search across every widget-mounting surface in the system" or "show me every consumer of `contacts.ssn` regardless of where it's mounted" needs a single iteration that spans Page+Dashboard+RecordDetail uniformly. Until that capability is load-bearing, the adapter is unwritten and Page-as-View is purely conceptual. Could be never. If a year from now the matrix asymmetries between the three surfaces erode (Dashboards gain publishing, RecordDetailViews gain sub-pages, etc.) and a unified `views` table becomes obvious — the interface is already in place, and merging tables is a migration, not a paradigm shift. The door to heavy unification stays open without paying the cost upfront.

## The honest framing

This is a digression, not a pivot. The product does not get better for users because of this work — it gets better for the solo developer maintaining it. The reason to do it is long-term maintainability and security posture, not feature velocity in any individual quarter. If Phase 1 succeeds, the payoff compounds over years. If it fails or the contract proves intractable, the preparation work done in v1 was cheap enough to absorb.

The architectural prize, if it works: a product where "can it do X" is by default yes, because X is a widget and widgets are how the product is built. Rimworld posture, stated plainly in the premise. This document is one possible path to earning that posture without rewriting v1.

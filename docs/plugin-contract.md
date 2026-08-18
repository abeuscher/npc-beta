# Plugin Contract

**Contract Version:** `0.6.0`
**Status:** active — Stage B (real packages, one workspace)
**Owner:** core (this repo). Plugins implement against this document.
**Canonical plan:** `sessions/plugin-architecture-plan.md` (architecture rationale, arc, question dispositions)

---

## What this document is

The single source of truth for the surface **core publishes to plugins**. A plugin is a self-contained module that extends core only through the surfaces listed here; core guarantees those surfaces and nothing else. An agent working inside a plugin should be able to substitute this document for core's code; an agent working in core must treat every PROVEN surface as a compatibility boundary.

**Marking discipline:** every surface below is marked **PROVEN** (exercised end-to-end by a shipped extraction, with tests) or **DECLARED** (specified, not yet exercised). A surface is only promoted to PROVEN in the session that exercises it. The version bumps FM-contract-style: additive clarifications bump the patch/minor version; changing a PROVEN surface's shape is a breaking change and bumps the major version once this doc reaches 1.0.0.

**Reference implementations:** the LogoGarden pilot at `plugins/LogoGarden/` (extracted session 377 — arc P1) for a widget plugin, the Payments foundation plugin at `plugins/Payments/` (extracted session 380 — arc P4) for a non-widget plugin owning services, an HTTP surface, an observer, and a capability declaration, and the Events vertical at `plugins/Events/` (extracted session 381 — arc P5) for a full domain vertical: public + portal controllers and routes, observers, policy, mailables, a scheduled command, a Filament resource + import pages through the admin socket, seven widgets (nested under `Widgets/`), an importer contribution, and an optional foundation-plugin dependency consumed through capability detection.

---

## In-repo plugin shape (Stage A)

```
plugins/{PascalName}/
    {PascalName}ServiceProvider.php   ← the single entry point
    ...everything else the plugin owns
```

- **Location:** top-level `plugins/{PascalName}/`.
- **Namespace:** `Plugins\{PascalName}\`, autoloaded via the `"Plugins\\": "plugins/"` PSR-4 entry in `composer.json`.
- **Wiring:** one line in `config/plugins.php` — an ordered list of plugin service-provider class strings, registered at bootstrap by core's `App\Providers\PluginServiceProvider` (listed in `bootstrap/providers.php` after `WidgetServiceProvider`, so core singletons exist before any plugin boots). **Remove the line and the plugin is gone**: no registration, no views, no synced `widget_types` row on the next `widgets:sync`. This list is the in-repo miniature of the future distribution-manifest / per-install-activation layer.

## Package shape (Stage B)

A Stage B plugin keeps the Stage A shape and folder location and additionally becomes a real composer package resolved through a path repository (first shipping example: `nonprofitcrm/logo-garden`, session 382 — arc P6):

```
plugins/{PascalName}/
    composer.json                     ← name, "type": "library", explicit "version",
                                        PSR-4 "Plugins\{PascalName}\": "", PHP constraint
    {PascalName}ServiceProvider.php   ← still the single entry point
    ...everything else the plugin owns
```

- **Resolution:** the root `composer.json` carries a `path` repository entry for the plugin folder and a bound version requirement; `composer install` symlinks `vendor/{vendor}/{package}` → the plugin folder. The package declares an explicit `version` field so resolution is deterministic everywhere the build runs without git metadata (the Docker image build excludes `.git/`).
- **Activation and ordering stay with `config/plugins.php`** — see surface 1. The package declares **no** `extra.laravel.providers`; Laravel auto-discovery is deliberately unused.
- **PSR-4 overlap (accepted, documented):** the root `"Plugins\\": "plugins/"` mapping still covers every in-repo plugin; a packaged plugin's own PSR-4 maps the same files. Both mappings resolve to identical paths, composer tolerates the overlap, and the root mapping retires per-plugin as each becomes a package.
- **Image builds:** plugin package manifests are part of the Dockerfile's manifests-before-source layer set — `plugins/{PascalName}/composer.json` is copied before both `composer install` layers so the path repository resolves while layer caching (source edits never bust the dependency layers) is preserved; the install-time symlink resolves fully once the full source lands.
- **Core dependency modeling** (the package requiring core at composer level) is deliberately absent at P6 — the package programs against this contract; modeling the dependency is P7+ territory.

---

## The 13 surfaces

### 1. Registration — **PROVEN** (session 377)

One service provider per plugin, listed in `config/plugins.php` and registered by core's `PluginServiceProvider` during bootstrap. **The config list is the sole activation + ordering authority through Stage B** (amended at session 382, superseding the earlier "Stage B replaces the config list with package auto-discovery" posture): packages declare no `extra.laravel.providers` and Laravel auto-discovery is deliberately unused, because the remove-the-line guarantee, the registries-before-plugins binding order, and plugin-boot-before-core-routes ordering are all load-bearing and all keyed on the config list — auto-discovery would move activation to composer edits and surrender ordering to installation order. The provider remains the single entry point at every stage; the config list is the in-repo miniature of the future distribution-manifest / per-install-activation layer (arc P8).

Proven by: `Plugins\LogoGarden\LogoGardenServiceProvider` — registered through the config list, asserted loaded in `tests/Feature/PluginPilotSession377Test.php`. Second shipping consumer (session 380): `Plugins\Payments\PaymentsServiceProvider`, the first non-widget plugin — its provider owns contract bindings and a capability declaration at `register()`, and config injection, an observer, and a route file at `boot()` (`PaymentsPluginSession380Test` + the removal mirror).

### 2. Widget contribution — **PROVEN** (session 377)

A plugin registers `App\Widgets\Contracts\WidgetDefinition` subclasses into the core `App\Services\WidgetRegistry` singleton from its provider's `boot()`. The registry API is core-owned and unchanged; `widgets:sync` remains the only writer of `widget_types` rows and is idempotent regardless of where a definition registers from.

- **Views:** the plugin registers its own Blade namespace in `boot()` (pilot: `plugin-logo-garden::`, pointing at the plugin folder) and overrides `template()` to include through it. The shared `widgets::` namespace (→ `app/Widgets`) stays core-only.
- **Thumbnails:** core resolves a widget's thumbnail directory via `WidgetDefinition::thumbnailDir()` — derived by reflection from the concrete definition class's own file location, so it holds in core and in plugins with no override. Ship `thumbnails/static.png` (and `preset-*.png`) next to the definition; `scripts/generate-thumbnails.js` writes to whichever of `app/Widgets/{Name}/` or `plugins/{Name}/` exists.
- **Data access (ENFORCED, session 378 — arc P2):** widget templates are pure renderers — they read only `$config` / `$configMedia` / `$widgetData` / `$pageContext` and render-layer helpers. No model calls, no `auth()`, no `app()`/`resolve()`/`@inject`, no `DB::`/`Auth::` in any template. The standing guard `tests/Feature/WidgetTemplateBoundaryTest.php` scans `app/Widgets/*/template.blade.php`, `plugins/*/template.blade.php`, and `resources/views/widget-shared/*` and fails on any such reach, with **no allowlist**. Core Blade components (`<x-picture>`, `<x-widget-buttons>`, …) are core view infrastructure and outside the scan.
- **Declaring contracts:** the singular `dataContract(array $config): ?DataContract` remains the common case. Widgets consuming several sources override `dataContracts(array $config): array` — named contracts (`['event' => …, 'member' => …]`); their templates receive `$widgetData` keyed by contract name, while single-contract widgets keep the unkeyed `['items' => …]` / `['item' => …]` shape. User query-config merges into the primary (first) contract only.
- **Sources & arms (exercised by the P2 retrofit):** `SOURCE_SYSTEM_MODEL` arms now include `fund`, `membership_tier`, `navigation_menu` (single, id filter), and `portal_member` (single; resolves the portal guard inside the arm, logged-out ⇒ `['item' => null]`, non-leak tested). `SOURCE_SERVICE` covers service-backed datasets (`setup_checklist`, `scrub_counts`) whose arms hard-gate on super-admin. `SOURCE_PAGE_CONTEXT` contracts may declare fields (e.g. `site_name`, a site-global token) for direct `$widgetData` reads. Every arm projects contract-declared fields only, fail-closed.
- **Widget folder paths:** `WidgetDefinition::baseDir()` resolves the widget's own folder by reflection (generalizing 377's `thumbnailDir()`); anything declared relative to the widget folder (thumbnails, screenshots) resolves through it in core and plugins alike.

**At-scale contributor (session 381):** the Events vertical registers seven widgets from one provider, nested at `plugins/Events/Widgets/{Name}/` (a vertical plugin's widget folders nest under `Widgets/`; single-widget plugins stay at the plugin root). Each definition overrides `template()` to include through the plugin's own widget view namespace, and template partials (`event-row`, `row`) include through the same namespace. Standing-guard globs (`WidgetTemplateBoundaryTest`, `WidgetColorTokenConsumptionTest`) and the thumbnail tooling cover the nested shape. `widgets:sync` keeps the seven `widget_types` rows (handles unchanged) with updated asset paths; a plugin widget may declare a **core** widget's stylesheet as a build input (EventsListing reuses core BlogPager's pager styles) — asset paths are repo-relative build inputs, not namespace reaches.

Proven by: the pilot's registration, sync-row, and view-namespace tests (`PluginPilotSession377Test`), render parity through the contract resolver (`LogoGardenContractRetrofitTest`), the all-41 retrofit's arm + parity tests (`WidgetBoundaryArmsSession378Test`, `WidgetBoundaryRetrofitSession378Test`), and the standing boundary guard (`WidgetTemplateBoundaryTest`).

### 3. Admin contribution — **PROVEN** (session 381)

A plugin declares its admin-shell contribution from its provider's `register()` by handing an `App\Plugins\AdminContribution` to the core `App\Plugins\PluginAdminRegistry` singleton (bound by `PluginServiceProvider` before any plugin registers; `AdminPanelProvider` reads the registry when Filament builds the panel — `bootstrap/providers.php` guarantees the ordering). The contribution declares:

- **Filament discovery paths** — `resourcesPath`/`resourcesNamespace` and `pagesPath`/`pagesNamespace`, joined to core's own `discoverResources`/`discoverPages` calls. `null` = the plugin contributes nothing on that axis.
- **Permission names** — seeded by core's `PermissionSeeder` (idempotent `firstOrCreate`, guard `web`) wherever core permission seeding runs; "enabled" in Stage A = listed in `config/plugins.php`. Granted to **no shipped role** — super-admin reaches via the `Gate::before` bypass (the `manage_account` precedent). Disabling a plugin stops the seeding but never deletes seeded rows (disabled-is-inert rule: they remain, gating nothing).
- **An in-panel route file** — see surface 4.

Three settled conventions (owner-approved, session 376), now mechanical:

1. Core owns the nav-group list (the five `->navigationGroups()` entries in `AdminPanelProvider`, the single source). A plugin's pages join a group with Filament-native `$navigationGroup` — which **must** name a core group; no plugin-invented groups in Stage A — and order themselves with `$navigationSort`.
2. A plugin declares its permissions in its registry entry; core seeds them at enable time, granted to no shipped role by default except super-admin.
3. Plugin admin pages inherit core's admin theme — plugins ship no admin CSS. **Enforced** by the standing guard `tests/Feature/PluginAdminCssGuardTest.php`: no `.css` files under `plugins/`, no `viteTheme`/render-hook stylesheet injection in plugin PHP. Widget-scoped SCSS/JS (surface 11) is legitimate and does not trip it.

Removing the plugin's `config/plugins.php` line removes the whole contribution — page, routes, permission seeding (the surface-1 remove-the-line guarantee, extended to the admin socket).

**Extracted-vertical permission nuance (session 381):** the permission channel above is for genuinely NEW permission names a plugin introduces. A vertical extracted from core keeps its pre-existing resource permissions (e.g. `view_any_event` … `delete_event`) in core's `PermissionSeeder` role matrix with their shipped-role grants — moving their seeding to the plugin channel would break the role matrix on a plugin-removed install (Spatie throws granting a missing permission). Core's role matrix stays whole until it becomes composable (arc P8 at the earliest); the Events plugin accordingly declares **no** permission names.

Proven by: the Events vertical (session 381, arc P5) — `EventResource` + four pages plus the two import wizard pages discovered from `plugins/Events/Filament/` via `resourcesPath`/`pagesPath`, landing on byte-identical route names/URIs/middleware (272/272 parity, action FQCNs excepted), nav placement (`CMS` group, sort 3) unchanged. The `SocketProbe` fixture (+ removal mirror) is **kept as a standing regression fixture** — it exercises the socket in isolation, including axes no shipping plugin currently uses. Removal mirror: `EventsPluginRemovalSession381Test` (admin resource + import pages gone).

### 4. Routes — **PROVEN** (session 381)

Core's own admin routes are the precedent: `AdminPanelProvider`'s former ~100-line inline `->routes()` closure is decomposed into per-feature files under `routes/admin/` (session 379, route-list parity verified) — each file self-contained, carrying its own middleware/prefixes/names, pulled into the panel's route group.

A plugin contributes the same shape: the `routesFile` path in its `AdminContribution` is pulled into the panel's `->routes()` group after core's files. **Core wraps every plugin pull in Filament's `Authenticate` middleware — a plugin admin route can never opt out of panel auth**; the plugin's file carries everything else (names, prefixes, extra middleware). Routes land inside the panel's base middleware stack (suspension gate, security headers, session, CSRF) and under the panel's name prefix (`filament.admin.`).

Front-of-house (non-panel) routes are the other half of this surface: a plugin's provider `loadRoutesFrom()`s its own route file, self-contained with middleware and names (the Payments webhook precedent, session 380). Provider `boot()` runs before core's `routes/web.php` loads (at app booted), so a plugin GET route registers ahead of the page-slug catch-all — the Events JSON endpoint depends on this ordering.

Proven by: the Events vertical (session 381) — `plugins/Events/routes/web.php` registers `events.register` (throttled POST), `portal.events.register` (portal-auth stack), and `api.events.json` (GET ahead of the catch-all) with identical names/URIs/middleware to their pre-carve core registrations; admin-page routes land through surface 3's discovery. The `routesFile` in-panel pull specifically remains exercised by the standing SocketProbe fixture (no shipping plugin needs an in-panel route file yet). Vanish: with the line removed the POST paths answer 405 (the GET catch-all owns every path) and the JSON path falls to the catch-all as 404 (`EventsPluginRemovalSession381Test`).

### 5. Migrations — **DECLARED**

`loadMigrationsFrom` per plugin. The squash-discipline interaction (core schema dump excludes plugin tables; deterministic install order core → enabled plugins → seeders; per-composition fresh-install identity check) is specified in the plan doc §6.7 and lands at P7, when the Events vertical becomes a package (scope split settled at the 381 close; the plain T1 squash landed at P6 with the boundary unchanged).

### 6. Seed / config packs — **DECLARED**

Per-plugin seeders plus the niche configuration layer. (The pilot ships its `DemoSeeder` inside the plugin namespace and tests invoke it there — the seeder *mechanics* work from `plugins/`; the surface stays DECLARED until the configuration-pack layer exists.)

### 7. Email templates — **DECLARED**

Handle registration replacing the hard-coded defaults array on `EmailTemplate` (`forHandle()`); a registry the plugin contributes to, mirroring the widget registry pattern.

### 8. Importer registration — **PROVEN** (session 381)

Importers ship with their domain plugin. A plugin declares an `App\Plugins\ImporterContribution` into the core `App\Plugins\ImporterRegistry` singleton (bound by `PluginServiceProvider`, populated at plugin `register()` — the surfaces-3/13 pattern). The contribution pairs a slug with:

- **`pageClass` / `progressPageClass`** — the Filament wizard + progress pages (discovered via the plugin's surface-3 `pagesPath`; the registry only tells core's import surfaces which classes pair with which slug).
- **`label` / `icon` / `modelType`** — the importer-hub card, including the blocked-while-a-session-is-reviewing state.
- **`templateHeaders`** (closure) — the downloadable CSV template's header row, resolved by `CsvTemplateService::headersFor($slug)`.
- **`fakeSourceFieldMap`** (closure) — the saved field map for the seeded demo import source.

The per-importer composition (header prefixes, contact-match columns, transaction columns) is importer domain knowledge and lives in the plugin's closures, not in core consumers. Core consumers that resolve through the registry: the importer hub page + cards, the CSV-template streamer, the fixture runner's slug→progress-page pairing, and the demo-source/fake-CSV dev commands (which skip absent importers). Field-registry base classes (`FieldRegistry`, `AggregatingRegistry`, `DerivesFromFillable`) and the core-owned registries a plugin aggregates against (contact match, transactions) stay core. **Core-owned importers stay hard-wired in their consumers** — they can join the registry when their domains extract.

Proven by: the events importer (session 381) — three field registries + the preset column mapper + both wizard pages shipped in `plugins/Events/`, every core import surface resolving through the registry (`EventsPluginSession381Test`); with the plugin line removed the hub shows no events card, `headersFor('events')` throws, the demo source is skipped, and the import pages are gone (`EventsPluginRemovalSession381Test`).

### 9. Contact extension — **DECLARED** *(normative rule settled)*

**Relations on `contact_id`, only** (owner ruling, 376). Plugins attach data via plugin-owned tables keyed by `contact_id` — never columns on core tables, never schemaless-blob co-mingling. Core `Contact` stays minimal identity. The contact admin screen will expose a socket where plugins contribute panels (arc P3+).

### 10. Events / hooks — **PROVEN** (session 381, scoped to the events slice)

Laravel events for cross-plugin reaction. Plugins listen to core and foundation-plugin events; they never call into another vertical.

**The inbound payments inversion (events slice, session 381 — arc P5):** core owns the typed event `App\Payments\Events\CheckoutSettled` (the Stripe Checkout Session object + its metadata). The Payments webhook keeps the metadata routing switch — the metadata key is written by the vertical at session creation; routing is payments infrastructure — but its `event_registration_checkout` branch body is now: dispatch `CheckoutSettled`, return 200. The Events plugin's listener (`Plugins\Events\Listeners\PromotePaidRegistrations`, registered from its provider) owns the entire former handler behavior: pending-promotion by `stripe_session_id`, contact resolution, one `Transaction::recordStripe` per order (a core write, allowed), one queued confirmation — idempotency identical (pending-only filter; a replay finds nothing pending). With Events absent the dispatch has no listener: 200, rows stay pending, data kept.

**Per-vertical rollout:** the donation, membership, product, generic-fallback, invoice, refund, and failed-intent branches stay inline in Payments — each inverts to this surface when its vertical extracts. That is the moment a webhook write would otherwise become a forbidden foundation→vertical reach.

Proven by: the dispatch/listener/replay/no-listener tests (`EventsPluginSession381Test`, `EventsPluginRemovalSession381Test`) and the pre-existing webhook behavioral net passing through the dispatch path with assertions unchanged (`MultiQuantityRegistrationTest`, `RegistrationConfirmationTest`).

### 11. Front-end assets — **PROVEN** (session 377)

A widget's `assets()` declares repo-relative `scss` / `js` file paths plus `libs` identifiers. `widgets:sync` writes them to `widget_types.assets`; `AssetBuildService::collectSources()` resolves each path via `base_path()` off disk — plugin paths (`plugins/{Name}/…`) resolve exactly like core paths. `build:public` compiles the bundle out-of-band on the build server and regenerates `public/build/widgets/manifest.json`.

**Caveat (by design, verify per extraction):** `collectSources()` silently skips paths that don't exist on disk — a stale path drops the asset from the bundle without erroring. After any asset-path change, run `widgets:sync` then `build:public` and confirm the bundle carries the plugin's content. The pilot's sync test asserts every declared asset path exists on disk.

Proven by: the pilot's SCSS + JS + `swiper` lib building from `plugins/LogoGarden/` into the served bundle (session 377, manifest + bundle-content verified).

### 12. Versioning — **PROVEN** (this document)

This contract doc is the versioned artifact, maintained with the same discipline as `docs/fleet-manager-agent-contract.md`: version header, changelog, both sides update before the next boundary-touching session. "Does this follow the contract?" is the arbiter that replaces most in-session judgment calls (execution posture, 376).

### 13. Capability detection / soft dependencies — **PROVEN** (session 380)

A consumer can ask "is capability X present and enabled?" and must degrade gracefully when it isn't. Mechanics (session 380, arc P4):

- **`App\Plugins\CapabilityRegistry`** — core-owned singleton, bound by `PluginServiceProvider` alongside `PluginAdminRegistry` before any plugin registers. A foundation plugin declares `provide(string $name, Closure $enabled)` from its provider's `register()`; consumers ask `present($name)` (a registered plugin provides it) and `enabled($name)` (present AND the lazy resolver returns true — evaluated on every call, never cached).
- **Capability-scoped contracts are core-owned.** Alongside the boolean question, core owns the interfaces a capability's consumers program against — `App\Payments\Contracts\CheckoutProvider` (checkout-session creation + per-flow default image; callers rely only on the returned object's `->id`/`->url`) and `App\Payments\Contracts\PaymentModeProvider` (live-credential detection, consumed via the static `App\Payments\PaymentMode::isLive()`, false when nothing binds — an install without payments is never live). The plugin binds its implementations at `register()`; **nothing in `app/` may reference the `Plugins\Payments\` namespace, the Stripe SDK, or `services.stripe.*` config** — enforced with zero allowlist by the standing guard `tests/Feature/Infrastructure/PaymentModuleBoundaryTest.php`.
- **`payments`** is the first capability: resolver `filled(config('services.stripe.secret'))`, the same truthiness the five pre-carve controller guards tested. Remove the plugin's `config/plugins.php` line and present/enabled are false, every checkout endpoint returns its existing not-configured response, the webhook route is gone, and the free/$0 paths run untouched (`PaymentsPluginRemovalSession380Test`).

Dependency rules (owner guideline, 376):

- **Verticals never depend on each other.**
- **Hard dependencies point only at core.**
- **Optional dependencies point at foundation plugins** (composer `suggests`, never `require`), consumed through capability detection — e.g. Events without Payments = free events only.

Proven by: five shipping vertical call sites (donation, product, membership, and both event-registration checkout controllers) plus `DashboardIntegrationStatusWidget` consuming `enabled('payments')`; the capability-semantics tests (`CapabilityRegistrySession380Test`) and the enabled/removal twin pair. **First in-plugin vertical consumer (session 381):** both event-registration controllers now consume `enabled('payments')` + `CheckoutProvider` from inside `plugins/Events/` — the vertical→foundation soft dependency in the flesh, tested across the full presence matrix (both present: the suite; Events without Payments: free/comp work, paid degrades — `EventsWithoutPaymentsSession381Test`; Events absent: the surface vanishes, data kept).

---

## Normative rules (apply to every plugin, every stage)

1. **Plugins never patch plugins** (plan principle 5). Extension happens through the surfaces above; two plugins never modify each other.
2. **Relations on `contact_id`, only** — see surface 9.
3. **Vertical/foundation dependency rules** — see surface 13.
4. **Disabled = inert, never destructive.** A disabled plugin registers no routes, contributes no nav, schedules no jobs, and stops writes — **its data is kept**. Enabling later is cheap; disabling never deletes. (Per-install activation lands at arc P8; the rule binds from day one.)
5. **Add-a-test discipline.** Every plugin carries tests for what it contributes; behavior a plugin adds is proven by tests that ship with the plugin (per-plugin suites formalize at Stage B, P7).
6. **Contract honesty.** Surfaces here are marked PROVEN only when a shipped extraction exercises them.

---

## Changelog

- **0.6.0** (session 382, 2026-08-18) — Stage B entry (arc P6): the LogoGarden pilot becomes the first true composer package (`nonprofitcrm/logo-garden` — own `composer.json` with explicit `version`, resolved through a root path repository into a vendor symlink; committed lockfile; `composer validate --strict` clean). New **Package shape (Stage B)** section records the shape, the deterministic-version rationale, the accepted PSR-4 overlap with the root `"Plugins\\": "plugins/"` mapping, and the Dockerfile manifests-before-source rule (plugin manifests copied before both `composer install` layers, layer caching preserved). **Surface 1's Stage B sentence amended** (owner-approved, 381 P6 recommendation): `config/plugins.php` stays the sole activation + ordering authority through Stage B; Laravel auto-discovery deliberately unused. Payments and Events remain plain in-repo folders until P7. Rides with the absorbed T1 migration squash (three live migrations collapsed into the regenerated dump, 348-precedent identity check; representational only — the events tables stay in core's dump until P7's boundary redraw).
- **0.5.0** (session 381, 2026-08-18) — the first domain vertical (arc P5): the Events behavioral surface carved into `plugins/Events/` (controllers/routes, quantities service, observers, three mailables, reminders command + schedule, policy, `EventResource` + four pages, two import wizard pages, seven widgets, the events importer). Surface 3 **PROVEN** (first shipping admin-page plugin; extracted-vertical permission nuance recorded — pre-existing resource permissions stay core-seeded, the plugin declares none). Surface 4 **PROVEN** (three public routes with byte-identical names/URIs/middleware; front-of-house route-file half documented; the in-panel `routesFile` pull stays fixture-covered by SocketProbe, kept as a standing regression fixture). Surface 8 **PROVEN**: core `ImporterContribution` + `ImporterRegistry`; the hub, CSV-template streamer, fixture runner, and demo-source seeding resolve plugin importers through it; core importers stay hard-wired until their domains extract. Surface 10 **PROVEN scoped to the events slice**: core `App\Payments\Events\CheckoutSettled` dispatched by the Payments webhook's events branch; the Events listener owns fulfillment; remaining branches invert per-vertical. Surface 2 notes the at-scale widget contributor (7 widgets, nested `Widgets/` shape, partials + assets). Surface 13 notes the first in-plugin vertical consumer + the tested presence matrix. Standing guard `EventModuleBoundaryTest` bans `Plugins\Events\` in `app/` with zero allowlist; core reaches the events admin surface only by route name, and the landing-page factory lives in core (`App\Services\EventLandingPageFactory`) because the models are core at Stage A. Models/schema/permissions stay core (P7's squash redraw moves the schema).
- **0.4.0** (session 380, 2026-08-18) — surface 13 PROVEN (arc P4): the Stripe rails carved into `plugins/Payments/`, the first shipping foundation plugin; core `CapabilityRegistry` (`provide`/`present`/`enabled`, lazy resolvers) bound by `PluginServiceProvider`; core-owned `App\Payments\Contracts\CheckoutProvider` + `PaymentModeProvider` contracts with the plugin binding implementations at `register()`; five vertical checkout call sites + the dashboard integration widget consume the API. Surface 1 gains its second shipping consumer (`PaymentsServiceProvider`). Surface 10 records the named P5 inbound-inversion plan. Standing guard `PaymentModuleBoundaryTest` bans the Stripe SDK, the plugin namespace, and `services.stripe.*` config reads in `app/` with zero allowlist. `laravel/cashier` removed (never used; two dead vendor routes dropped), `stripe/stripe-php` now a direct dependency.
- **0.3.0** (session 379, 2026-08-18) — surfaces 3 and 4's mechanics built and fixture-tested (arc P3), statuses kept DECLARED per the honesty rule. Admin socket: `App\Plugins\AdminContribution` + `App\Plugins\PluginAdminRegistry` (populated at plugin `register()`, consumed by `AdminPanelProvider`) declaring Filament discovery paths, an in-panel route file (always wrapped in `Authenticate` by core), and permission names (seeded idempotently by `PermissionSeeder`, granted to no shipped role). Core's inline `->routes()` closure decomposed into per-feature `routes/admin/` files with exact route-list parity. The three session-376 Filament conventions made mechanical; convention 3 enforced by the standing guard `PluginAdminCssGuardTest` (no plugin admin CSS, widget SCSS exempt). Fixture: `tests/Fixtures/Plugins/SocketProbe/` with enabled + removal test mirrors.
- **0.2.0** (session 378, 2026-08-18) — surface 2's template-purity rule made real across all 41 widgets (arc P2): the twelve model/auth/service-reaching templates routed through declared contracts; `dataContracts()` (named, multi-source) added alongside the singular `dataContract()`; new resolver arms `fund`, `membership_tier`, `navigation_menu`, `portal_member` and the `SOURCE_SERVICE` source (`setup_checklist`, `scrub_counts`, super-admin-gated in the arm); `site_name` added to the page-context token set; `WidgetDefinition::baseDir()` generalizes folder-relative resolution; standing guard `WidgetTemplateBoundaryTest` enforces the boundary with zero allowlist.
- **0.1.0** (session 377, 2026-08-18) — initial draft from the LogoGarden pilot extraction (arc P1). Surfaces 1, 2, 11, 12 PROVEN; all others DECLARED. In-repo plugin shape (`plugins/` + `config/plugins.php` + `PluginServiceProvider` loader) established. Normative rules recorded from the session-376 dispositions.

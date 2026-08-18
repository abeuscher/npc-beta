# Plugin Contract

**Contract Version:** `0.4.0`
**Status:** active — Stage A (in-repo module boundaries)
**Owner:** core (this repo). Plugins implement against this document.
**Canonical plan:** `sessions/plugin-architecture-plan.md` (architecture rationale, arc, question dispositions)

---

## What this document is

The single source of truth for the surface **core publishes to plugins**. A plugin is a self-contained module that extends core only through the surfaces listed here; core guarantees those surfaces and nothing else. An agent working inside a plugin should be able to substitute this document for core's code; an agent working in core must treat every PROVEN surface as a compatibility boundary.

**Marking discipline:** every surface below is marked **PROVEN** (exercised end-to-end by a shipped extraction, with tests) or **DECLARED** (specified, not yet exercised). A surface is only promoted to PROVEN in the session that exercises it. The version bumps FM-contract-style: additive clarifications bump the patch/minor version; changing a PROVEN surface's shape is a breaking change and bumps the major version once this doc reaches 1.0.0.

**Reference implementations:** the LogoGarden pilot at `plugins/LogoGarden/` (extracted session 377 — arc P1) for a widget plugin, and the Payments foundation plugin at `plugins/Payments/` (extracted session 380 — arc P4) for a non-widget plugin owning services, an HTTP surface, an observer, and a capability declaration.

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

---

## The 13 surfaces

### 1. Registration — **PROVEN** (session 377)

One service provider per plugin. Stage A: the provider class is listed in `config/plugins.php` and registered by core's `PluginServiceProvider` during bootstrap. Stage B replaces the config list with Laravel package auto-discovery per composer package; the provider remains the single entry point either way.

Proven by: `Plugins\LogoGarden\LogoGardenServiceProvider` — registered through the config list, asserted loaded in `tests/Feature/PluginPilotSession377Test.php`. Second shipping consumer (session 380): `Plugins\Payments\PaymentsServiceProvider`, the first non-widget plugin — its provider owns contract bindings and a capability declaration at `register()`, and config injection, an observer, and a route file at `boot()` (`PaymentsPluginSession380Test` + the removal mirror).

### 2. Widget contribution — **PROVEN** (session 377)

A plugin registers `App\Widgets\Contracts\WidgetDefinition` subclasses into the core `App\Services\WidgetRegistry` singleton from its provider's `boot()`. The registry API is core-owned and unchanged; `widgets:sync` remains the only writer of `widget_types` rows and is idempotent regardless of where a definition registers from.

- **Views:** the plugin registers its own Blade namespace in `boot()` (pilot: `plugin-logo-garden::`, pointing at the plugin folder) and overrides `template()` to include through it. The shared `widgets::` namespace (→ `app/Widgets`) stays core-only.
- **Thumbnails:** core resolves a widget's thumbnail directory via `WidgetDefinition::thumbnailDir()` — derived by reflection from the concrete definition class's own file location, so it holds in core and in plugins with no override. Ship `thumbnails/static.png` (and `preset-*.png`) next to the definition; `scripts/generate-thumbnails.js` writes to whichever of `app/Widgets/{Name}/` or `plugins/{Name}/` exists.
- **Data access (ENFORCED, session 378 — arc P2):** widget templates are pure renderers — they read only `$config` / `$configMedia` / `$widgetData` / `$pageContext` and render-layer helpers. No model calls, no `auth()`, no `app()`/`resolve()`/`@inject`, no `DB::`/`Auth::` in any template. The standing guard `tests/Feature/WidgetTemplateBoundaryTest.php` scans `app/Widgets/*/template.blade.php`, `plugins/*/template.blade.php`, and `resources/views/widget-shared/*` and fails on any such reach, with **no allowlist**. Core Blade components (`<x-picture>`, `<x-widget-buttons>`, …) are core view infrastructure and outside the scan.
- **Declaring contracts:** the singular `dataContract(array $config): ?DataContract` remains the common case. Widgets consuming several sources override `dataContracts(array $config): array` — named contracts (`['event' => …, 'member' => …]`); their templates receive `$widgetData` keyed by contract name, while single-contract widgets keep the unkeyed `['items' => …]` / `['item' => …]` shape. User query-config merges into the primary (first) contract only.
- **Sources & arms (exercised by the P2 retrofit):** `SOURCE_SYSTEM_MODEL` arms now include `fund`, `membership_tier`, `navigation_menu` (single, id filter), and `portal_member` (single; resolves the portal guard inside the arm, logged-out ⇒ `['item' => null]`, non-leak tested). `SOURCE_SERVICE` covers service-backed datasets (`setup_checklist`, `scrub_counts`) whose arms hard-gate on super-admin. `SOURCE_PAGE_CONTEXT` contracts may declare fields (e.g. `site_name`, a site-global token) for direct `$widgetData` reads. Every arm projects contract-declared fields only, fail-closed.
- **Widget folder paths:** `WidgetDefinition::baseDir()` resolves the widget's own folder by reflection (generalizing 377's `thumbnailDir()`); anything declared relative to the widget folder (thumbnails, screenshots) resolves through it in core and plugins alike.

Proven by: the pilot's registration, sync-row, and view-namespace tests (`PluginPilotSession377Test`), render parity through the contract resolver (`LogoGardenContractRetrofitTest`), the all-41 retrofit's arm + parity tests (`WidgetBoundaryArmsSession378Test`, `WidgetBoundaryRetrofitSession378Test`), and the standing boundary guard (`WidgetTemplateBoundaryTest`).

### 3. Admin contribution — **DECLARED** *(mechanics built and fixture-tested, session 379 — arc P3)*

A plugin declares its admin-shell contribution from its provider's `register()` by handing an `App\Plugins\AdminContribution` to the core `App\Plugins\PluginAdminRegistry` singleton (bound by `PluginServiceProvider` before any plugin registers; `AdminPanelProvider` reads the registry when Filament builds the panel — `bootstrap/providers.php` guarantees the ordering). The contribution declares:

- **Filament discovery paths** — `resourcesPath`/`resourcesNamespace` and `pagesPath`/`pagesNamespace`, joined to core's own `discoverResources`/`discoverPages` calls. `null` = the plugin contributes nothing on that axis.
- **Permission names** — seeded by core's `PermissionSeeder` (idempotent `firstOrCreate`, guard `web`) wherever core permission seeding runs; "enabled" in Stage A = listed in `config/plugins.php`. Granted to **no shipped role** — super-admin reaches via the `Gate::before` bypass (the `manage_account` precedent). Disabling a plugin stops the seeding but never deletes seeded rows (disabled-is-inert rule: they remain, gating nothing).
- **An in-panel route file** — see surface 4.

Three settled conventions (owner-approved, session 376), now mechanical:

1. Core owns the nav-group list (the five `->navigationGroups()` entries in `AdminPanelProvider`, the single source). A plugin's pages join a group with Filament-native `$navigationGroup` — which **must** name a core group; no plugin-invented groups in Stage A — and order themselves with `$navigationSort`.
2. A plugin declares its permissions in its registry entry; core seeds them at enable time, granted to no shipped role by default except super-admin.
3. Plugin admin pages inherit core's admin theme — plugins ship no admin CSS. **Enforced** by the standing guard `tests/Feature/PluginAdminCssGuardTest.php`: no `.css` files under `plugins/`, no `viteTheme`/render-hook stylesheet injection in plugin PHP. Widget-scoped SCSS/JS (surface 11) is legitimate and does not trip it.

Removing the plugin's `config/plugins.php` line removes the whole contribution — page, routes, permission seeding (the surface-1 remove-the-line guarantee, extended to the admin socket).

Fixture-tested by `tests/Feature/PluginAdminSocketSession379Test.php` (+ the removal mirror `PluginAdminSocketRemovalSession379Test.php`) via the `SocketProbe` fixture plugin at `tests/Fixtures/Plugins/SocketProbe/` — one page in a core nav group with a sort weight, one route file, one permission. **Stays DECLARED per the honesty rule**: promotion to PROVEN waits for the first shipping plugin with admin pages (P5+).

### 4. Routes — **DECLARED** *(mechanics built and fixture-tested, session 379 — arc P3)*

Core's own admin routes are the precedent: `AdminPanelProvider`'s former ~100-line inline `->routes()` closure is decomposed into per-feature files under `routes/admin/` (session 379, route-list parity verified) — each file self-contained, carrying its own middleware/prefixes/names, pulled into the panel's route group.

A plugin contributes the same shape: the `routesFile` path in its `AdminContribution` is pulled into the panel's `->routes()` group after core's files. **Core wraps every plugin pull in Filament's `Authenticate` middleware — a plugin admin route can never opt out of panel auth**; the plugin's file carries everything else (names, prefixes, extra middleware). Routes land inside the panel's base middleware stack (suspension gate, security headers, session, CSRF) and under the panel's name prefix (`filament.admin.`).

Fixture-tested by the same SocketProbe tests as surface 3. **Stays DECLARED per the honesty rule** until a shipping plugin registers routes (P5+).

### 5. Migrations — **DECLARED**

`loadMigrationsFrom` per plugin. The squash-discipline interaction (core schema dump excludes plugin tables; deterministic install order core → enabled plugins → seeders; per-composition fresh-install identity check) is specified in the plan doc §6.7 and lands with the first domain-vertical extraction (P5/P6).

### 6. Seed / config packs — **DECLARED**

Per-plugin seeders plus the niche configuration layer. (The pilot ships its `DemoSeeder` inside the plugin namespace and tests invoke it there — the seeder *mechanics* work from `plugins/`; the surface stays DECLARED until the configuration-pack layer exists.)

### 7. Email templates — **DECLARED**

Handle registration replacing the hard-coded defaults array on `EmailTemplate` (`forHandle()`); a registry the plugin contributes to, mirroring the widget registry pattern.

### 8. Importer registration — **DECLARED**

Registry entries (`app/Importers/*FieldRegistry.php` precedent) plus the Filament import-page pairing. Importers ship with their domain plugin.

### 9. Contact extension — **DECLARED** *(normative rule settled)*

**Relations on `contact_id`, only** (owner ruling, 376). Plugins attach data via plugin-owned tables keyed by `contact_id` — never columns on core tables, never schemaless-blob co-mingling. Core `Contact` stays minimal identity. The contact admin screen will expose a socket where plugins contribute panels (arc P3+).

### 10. Events / hooks — **DECLARED**

Laravel events for cross-plugin reaction (e.g. Donations fires, Mailchimp listens). Plugins listen to core and foundation-plugin events; they never call into another vertical.

**Named P5 work — the inbound payments inversion (decided at session 380, not silently deferred):** the Payments plugin's webhook controller still writes vertical models directly (Donation, EventRegistration, Membership, Purchase) — a plugin→core hard dependency, allowed today. When the first domain vertical extracts (arc P5), its webhook handlers invert to this surface: the Payments module emits typed payment-settled events and the vertical listens, scoped per-vertical as each one extracts. That is the moment a webhook write would otherwise become a forbidden plugin→vertical reach.

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

Proven by: five shipping vertical call sites (donation, product, membership, and both event-registration checkout controllers) plus `DashboardIntegrationStatusWidget` consuming `enabled('payments')`; the capability-semantics tests (`CapabilityRegistrySession380Test`) and the enabled/removal twin pair.

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

- **0.4.0** (session 380, 2026-08-18) — surface 13 PROVEN (arc P4): the Stripe rails carved into `plugins/Payments/`, the first shipping foundation plugin; core `CapabilityRegistry` (`provide`/`present`/`enabled`, lazy resolvers) bound by `PluginServiceProvider`; core-owned `App\Payments\Contracts\CheckoutProvider` + `PaymentModeProvider` contracts with the plugin binding implementations at `register()`; five vertical checkout call sites + the dashboard integration widget consume the API. Surface 1 gains its second shipping consumer (`PaymentsServiceProvider`). Surface 10 records the named P5 inbound-inversion plan. Standing guard `PaymentModuleBoundaryTest` bans the Stripe SDK, the plugin namespace, and `services.stripe.*` config reads in `app/` with zero allowlist. `laravel/cashier` removed (never used; two dead vendor routes dropped), `stripe/stripe-php` now a direct dependency.
- **0.3.0** (session 379, 2026-08-18) — surfaces 3 and 4's mechanics built and fixture-tested (arc P3), statuses kept DECLARED per the honesty rule. Admin socket: `App\Plugins\AdminContribution` + `App\Plugins\PluginAdminRegistry` (populated at plugin `register()`, consumed by `AdminPanelProvider`) declaring Filament discovery paths, an in-panel route file (always wrapped in `Authenticate` by core), and permission names (seeded idempotently by `PermissionSeeder`, granted to no shipped role). Core's inline `->routes()` closure decomposed into per-feature `routes/admin/` files with exact route-list parity. The three session-376 Filament conventions made mechanical; convention 3 enforced by the standing guard `PluginAdminCssGuardTest` (no plugin admin CSS, widget SCSS exempt). Fixture: `tests/Fixtures/Plugins/SocketProbe/` with enabled + removal test mirrors.
- **0.2.0** (session 378, 2026-08-18) — surface 2's template-purity rule made real across all 41 widgets (arc P2): the twelve model/auth/service-reaching templates routed through declared contracts; `dataContracts()` (named, multi-source) added alongside the singular `dataContract()`; new resolver arms `fund`, `membership_tier`, `navigation_menu`, `portal_member` and the `SOURCE_SERVICE` source (`setup_checklist`, `scrub_counts`, super-admin-gated in the arm); `site_name` added to the page-context token set; `WidgetDefinition::baseDir()` generalizes folder-relative resolution; standing guard `WidgetTemplateBoundaryTest` enforces the boundary with zero allowlist.
- **0.1.0** (session 377, 2026-08-18) — initial draft from the LogoGarden pilot extraction (arc P1). Surfaces 1, 2, 11, 12 PROVEN; all others DECLARED. In-repo plugin shape (`plugins/` + `config/plugins.php` + `PluginServiceProvider` loader) established. Normative rules recorded from the session-376 dispositions.

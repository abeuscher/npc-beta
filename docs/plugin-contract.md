# Plugin Contract

**Contract Version:** `0.2.0`
**Status:** active — Stage A (in-repo module boundaries)
**Owner:** core (this repo). Plugins implement against this document.
**Canonical plan:** `sessions/plugin-architecture-plan.md` (architecture rationale, arc, question dispositions)

---

## What this document is

The single source of truth for the surface **core publishes to plugins**. A plugin is a self-contained module that extends core only through the surfaces listed here; core guarantees those surfaces and nothing else. An agent working inside a plugin should be able to substitute this document for core's code; an agent working in core must treat every PROVEN surface as a compatibility boundary.

**Marking discipline:** every surface below is marked **PROVEN** (exercised end-to-end by a shipped extraction, with tests) or **DECLARED** (specified, not yet exercised). A surface is only promoted to PROVEN in the session that exercises it. The version bumps FM-contract-style: additive clarifications bump the patch/minor version; changing a PROVEN surface's shape is a breaking change and bumps the major version once this doc reaches 1.0.0.

**Reference implementation:** the LogoGarden pilot at `plugins/LogoGarden/` (extracted session 377 — arc P1). One folder: definition, service provider, template, SCSS, JS, demo seeder, thumbnails.

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

Proven by: `Plugins\LogoGarden\LogoGardenServiceProvider` — registered through the config list, asserted loaded in `tests/Feature/PluginPilotSession377Test.php`.

### 2. Widget contribution — **PROVEN** (session 377)

A plugin registers `App\Widgets\Contracts\WidgetDefinition` subclasses into the core `App\Services\WidgetRegistry` singleton from its provider's `boot()`. The registry API is core-owned and unchanged; `widgets:sync` remains the only writer of `widget_types` rows and is idempotent regardless of where a definition registers from.

- **Views:** the plugin registers its own Blade namespace in `boot()` (pilot: `plugin-logo-garden::`, pointing at the plugin folder) and overrides `template()` to include through it. The shared `widgets::` namespace (→ `app/Widgets`) stays core-only.
- **Thumbnails:** core resolves a widget's thumbnail directory via `WidgetDefinition::thumbnailDir()` — derived by reflection from the concrete definition class's own file location, so it holds in core and in plugins with no override. Ship `thumbnails/static.png` (and `preset-*.png`) next to the definition; `scripts/generate-thumbnails.js` writes to whichever of `app/Widgets/{Name}/` or `plugins/{Name}/` exists.
- **Data access (ENFORCED, session 378 — arc P2):** widget templates are pure renderers — they read only `$config` / `$configMedia` / `$widgetData` / `$pageContext` and render-layer helpers. No model calls, no `auth()`, no `app()`/`resolve()`/`@inject`, no `DB::`/`Auth::` in any template. The standing guard `tests/Feature/WidgetTemplateBoundaryTest.php` scans `app/Widgets/*/template.blade.php`, `plugins/*/template.blade.php`, and `resources/views/widget-shared/*` and fails on any such reach, with **no allowlist**. Core Blade components (`<x-picture>`, `<x-widget-buttons>`, …) are core view infrastructure and outside the scan.
- **Declaring contracts:** the singular `dataContract(array $config): ?DataContract` remains the common case. Widgets consuming several sources override `dataContracts(array $config): array` — named contracts (`['event' => …, 'member' => …]`); their templates receive `$widgetData` keyed by contract name, while single-contract widgets keep the unkeyed `['items' => …]` / `['item' => …]` shape. User query-config merges into the primary (first) contract only.
- **Sources & arms (exercised by the P2 retrofit):** `SOURCE_SYSTEM_MODEL` arms now include `fund`, `membership_tier`, `navigation_menu` (single, id filter), and `portal_member` (single; resolves the portal guard inside the arm, logged-out ⇒ `['item' => null]`, non-leak tested). `SOURCE_SERVICE` covers service-backed datasets (`setup_checklist`, `scrub_counts`) whose arms hard-gate on super-admin. `SOURCE_PAGE_CONTEXT` contracts may declare fields (e.g. `site_name`, a site-global token) for direct `$widgetData` reads. Every arm projects contract-declared fields only, fail-closed.
- **Widget folder paths:** `WidgetDefinition::baseDir()` resolves the widget's own folder by reflection (generalizing 377's `thumbnailDir()`); anything declared relative to the widget folder (thumbnails, screenshots) resolves through it in core and plugins alike.

Proven by: the pilot's registration, sync-row, and view-namespace tests (`PluginPilotSession377Test`), render parity through the contract resolver (`LogoGardenContractRetrofitTest`), the all-41 retrofit's arm + parity tests (`WidgetBoundaryArmsSession378Test`, `WidgetBoundaryRetrofitSession378Test`), and the standing boundary guard (`WidgetTemplateBoundaryTest`).

### 3. Admin contribution — **DECLARED**

Filament resources/pages via per-package discovery paths; nav-group self-assignment. Three settled conventions (owner-approved, session 376):

1. Core owns the nav-group list; a plugin declares which group its pages join, plus a sort weight.
2. A plugin declares its permissions; core seeds them at enable time, granted to no shipped role by default except super-admin (the `manage_account` precedent).
3. Plugin admin pages inherit core's admin theme — plugins ship no admin CSS.

Lands at arc P3 (panel-provider decomposition).

### 4. Routes — **DECLARED**

Per-plugin route files, loaded by the plugin's provider. Precedent: the three admin-builder API route files pulled in by the panel provider. Lands at P3.

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

### 11. Front-end assets — **PROVEN** (session 377)

A widget's `assets()` declares repo-relative `scss` / `js` file paths plus `libs` identifiers. `widgets:sync` writes them to `widget_types.assets`; `AssetBuildService::collectSources()` resolves each path via `base_path()` off disk — plugin paths (`plugins/{Name}/…`) resolve exactly like core paths. `build:public` compiles the bundle out-of-band on the build server and regenerates `public/build/widgets/manifest.json`.

**Caveat (by design, verify per extraction):** `collectSources()` silently skips paths that don't exist on disk — a stale path drops the asset from the bundle without erroring. After any asset-path change, run `widgets:sync` then `build:public` and confirm the bundle carries the plugin's content. The pilot's sync test asserts every declared asset path exists on disk.

Proven by: the pilot's SCSS + JS + `swiper` lib building from `plugins/LogoGarden/` into the served bundle (session 377, manifest + bundle-content verified).

### 12. Versioning — **PROVEN** (this document)

This contract doc is the versioned artifact, maintained with the same discipline as `docs/fleet-manager-agent-contract.md`: version header, changelog, both sides update before the next boundary-touching session. "Does this follow the contract?" is the arbiter that replaces most in-session judgment calls (execution posture, 376).

### 13. Capability detection / soft dependencies — **DECLARED** *(normative rules settled)*

A plugin can ask "is capability X present and enabled?" and must degrade gracefully when it isn't. Dependency rules (owner guideline, 376):

- **Verticals never depend on each other.**
- **Hard dependencies point only at core.**
- **Optional dependencies point at foundation plugins** (composer `suggests`, never `require`), consumed through capability detection — e.g. Events without Payments = free events only.

The detection API lands at arc P4 (Payments foundation module).

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

- **0.2.0** (session 378, 2026-08-18) — surface 2's template-purity rule made real across all 41 widgets (arc P2): the twelve model/auth/service-reaching templates routed through declared contracts; `dataContracts()` (named, multi-source) added alongside the singular `dataContract()`; new resolver arms `fund`, `membership_tier`, `navigation_menu`, `portal_member` and the `SOURCE_SERVICE` source (`setup_checklist`, `scrub_counts`, super-admin-gated in the arm); `site_name` added to the page-context token set; `WidgetDefinition::baseDir()` generalizes folder-relative resolution; standing guard `WidgetTemplateBoundaryTest` enforces the boundary with zero allowlist.
- **0.1.0** (session 377, 2026-08-18) — initial draft from the LogoGarden pilot extraction (arc P1). Surfaces 1, 2, 11, 12 PROVEN; all others DECLARED. In-repo plugin shape (`plugins/` + `config/plugins.php` + `PluginServiceProvider` loader) established. Normative rules recorded from the session-376 dispositions.

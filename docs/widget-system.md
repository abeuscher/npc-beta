# Widget System

Part of the multi-session Sovereign Widget arc (sessions 170+). This document describes the code-level widget authoring pattern.

---

## Overview

Every widget is a self-contained folder under `app/Widgets/{PascalName}/` holding its definition class, Blade template, and (optionally) SCSS. The application registers each definition at boot time and syncs them into the `widget_types` table.

The database remains the runtime source of truth. The renderer, inspector, and widget picker all read from `widget_types`. The definition class is the code-level authoring surface that writes to the DB via a sync step.

---

## File layout

Each widget is one folder at `app/Widgets/{PascalName}/`:

```
app/Widgets/Nav/
├── NavDefinition.php        # the definition class
├── template.blade.php       # the Blade template (required for render_mode = server)
└── styles.scss              # optional — present only if the widget has SCSS
```

- The folder is PascalCase (e.g. `Nav`, `BlogPager`, `ThreeBuckets`).
- The class is `{PascalName}Definition` in the matching namespace `App\Widgets\{PascalName}`.
- The widget's **handle** (which may be snake_case — e.g. `bar_chart`, `portal_signup`) lives in `handle()` / the DB row. Folder name and handle are independent.

---

## Blade namespace

`WidgetServiceProvider::boot()` registers a `widgets` Blade namespace pointing at `app_path('Widgets')`:

```php
View::addNamespace('widgets', app_path('Widgets'));
```

After registration, `@include('widgets::Nav.template')` resolves to `app/Widgets/Nav/template.blade.php`.

The base-class `template()` default produces that include automatically, so a widget with a standard `template.blade.php` file never needs to override `template()`:

```php
public function template(): string
{
    $folder = Str::replaceLast('Definition', '', class_basename(static::class));
    return "@include('widgets::" . $folder . ".template')";
}
```

---

## Components

### `App\Widgets\Contracts\WidgetDefinition`

Abstract base class. Concrete widget definitions extend it.

**Required methods (abstract):**

- `handle(): string` — unique handle (e.g. `'nav'`)
- `label(): string`
- `description(): string`
- `schema(): array` — field definitions in the same shape as the legacy `config_schema`
- `defaults(): array` — key/value map of defaults; every key declared in `schema()` must appear here

**Optional methods (defaulted):**

- `template()` → `"@include('widgets::{Folder}.template')"` (the base-class default; override only for inline Blade bodies)
- `category()` → `['content']`
- `collections()` → `[]`
- `assets()` → `[]`
- `fullWidth()` → `false`
- `defaultOpen()` → `false`
- `allowedPageTypes()` → `null` (meaning "all")
- `renderMode()` → `'server'`
- `requiredConfig()` → `null`
- `css()` → `null`
- `js()` → `null`
- `code()` → `null`
- `variableName()` → `null`
- `version()` → `'1.0.0'`
- `author()` → `'Nonprofit CRM'`
- `license()` → `'MIT'`
- `screenshots()` → `[]`
- `keywords()` → `[]`
- `presets()` → `[]`

**Introspection / validation:**

- `toRow()` — returns the array shape `WidgetType::updateOrCreate()` expects.
- `validate()` — throws when `defaults()` is missing a key declared in `schema()`.
- `manifest()` — returns the aggregated metadata array (handle, label, description, category, version, author, license, screenshots, keywords, presets). Consumed by the widget browser UI; never written to the DB.

### `App\Services\WidgetRegistry`

Singleton service. Holds registered definitions keyed by handle.

- `register(WidgetDefinition $def): void`
- `all(): array`
- `find(string $handle): ?WidgetDefinition`
- `manifests(): array` — handle → `$def->manifest()` for every registered definition. Called by the widget browser UI to populate its grid in one pass.
- `sync(): void` — iterates all definitions, calls `validate()`, then `WidgetType::updateOrCreate(['handle' => $def->handle()], $def->toRow())`.

### `App\Providers\WidgetServiceProvider`

- `register()` registers the `WidgetRegistry` singleton.
- `boot()` registers the Blade `widgets` namespace and every concrete definition via `$registry->register(new ...Definition())`.

### Seeder integration

`WidgetTypeSeeder::run()` is trivial: it removes a small number of legacy handles (`event_dates`, `hero_fullsize`, `site_header`, `site_footer`) that may linger from earlier schemas, then calls `app(WidgetRegistry::class)->sync()`.

---

## Adding a new widget

1. Create `app/Widgets/{PascalName}/` folder.
2. Create `{PascalName}Definition.php` extending `WidgetDefinition`, implementing the required methods.
3. Create `template.blade.php` in the same folder. The base-class default `template()` method will find it via the `widgets::` namespace.
4. (Optional) Create `styles.scss` in the same folder and reference it in `assets()`:
   ```php
   public function assets(): array
   {
       return ['scss' => ['app/Widgets/{PascalName}/styles.scss']];
   }
   ```
5. Register it in `WidgetServiceProvider::boot()`: `$registry->register(new \App\Widgets\{PascalName}\{PascalName}Definition());`
6. Run `php artisan db:seed --class=WidgetTypeSeeder` (or `migrate:fresh --seed`) to write the row.
7. If the widget has SCSS, run `php artisan build:public` to rebuild the public bundle.

---

## Shared Blade fragments

Reusable Blade fragments that multiple widgets include (buttons, share icons, icon components) live under `resources/views/widget-shared/`. They are **not** widgets and don't belong inside any single widget's folder.

Include them via the default view namespace:

```blade
@include('widget-shared.buttons', ['buttons' => $ctas, 'alignment' => $buttonAlignment])
@include('widget-shared.icon-github')
@include('widget-shared.share-icons.' . $platform)
```

---

## Config resolution

`App\Services\WidgetConfigResolver` is a stateless singleton that composes a widget's effective config at read time. This replaces per-template `$config['x'] ?? 'fallback'` defensiveness.

### Composition order

For any `PageWidget`, the resolver produces:

```
defaults → theme overrides (stub) → instance config
```

Later layers overwrite earlier ones key-by-key.

- **defaults:** from `WidgetDefinition::defaults()` if the widget handle is registered, otherwise from `WidgetType::getDefaultConfig()` (which reads `config_schema[].default`). The fallback branch is a safety net for DB rows whose definition is no longer registered at runtime (handle drift, uninstalled widget, etc.) — in normal operation every widget has a definition and the fallback is unused.
- **theme overrides:** a Stage-N extension point. Currently returns `[]`. Future template/theme-level defaults will compose in here without any per-widget template change.
- **instance config:** the contents of `page_widgets.config` — sparse, containing only the user's explicit overrides.

### Public surface

- `resolve(PageWidget $pw): array` — the merged config. Used by `WidgetRenderer::render()` before passing to Blade.
- `resolvedDefaults(PageWidget $pw): array` — defaults + theme overrides, without the instance layer. Shipped to the Vue inspector as the `resolved_defaults` payload field so the inspector and the renderer draw from the same source of truth.
- `hasOverride(PageWidget $pw, string $key): bool` — true when the instance explicitly overrides a key.
- `defaultFor(PageWidget $pw, string $key): mixed` — the value the resolver would use if the instance did not override the key.

### Sparse instance configs

New widgets are created with `config = []`. On save, `PageBuilderApiController::update()` strips any key whose submitted value equals the resolved default (strict `===`). The result: `page_widgets.config` stores only the user's explicit overrides. Future changes to `defaults()` propagate automatically to untouched fields on existing widgets.

Pre-existing fat rows (created before session 171) continue rendering correctly because the resolver composes over them; they will be naturally slimmed the next time a user saves.

### The invariant

Because both the renderer and the inspector draw defaults from the resolver, the inspector's displayed value for any field always equals the value the renderer uses for that field. There is no path where the two can diverge.

---

## Manifest & Metadata

Every widget declares human-facing metadata — version, author, license, screenshots, keywords, presets — via optional methods on its definition class. Metadata is **code-only**: it lives on the definition, not in the `widget_types` table, and is not part of `toRow()`. The widget browser UI reads it at runtime via `WidgetRegistry::manifests()`.

### Methods

| Method | Default | Purpose |
|--------|---------|---------|
| `version(): string` | `'1.0.0'` | Semver. Must match `/^\d+\.\d+\.\d+$/`. |
| `author(): string` | `'Nonprofit CRM'` | Attribution. |
| `license(): string` | `'MIT'` | One of `MIT`, `Apache-2.0`, `GPL-3.0`, `BSD-3-Clause`, `proprietary`. |
| `screenshots(): array` | `[]` | List of paths relative to the widget's folder (e.g. `'screenshots/hero.png'`). |
| `keywords(): array` | `[]` | Lowercase slug tags for browser search. Each must match `/^[a-z0-9-]+$/`. |
| `presets(): array` | `[]` | List of named config bundles (see shape below). |

All six are optional. Every widget is first-party, so the defaults are expected to cover `author` and `license`; override only when a widget has a genuine reason to differ (e.g. a future third-party widget).

### `manifest()` aggregator

The base class ships a `manifest(): array` that returns the full metadata surface in one shape:

```php
[
    'handle'      => $this->handle(),
    'label'       => $this->label(),
    'description' => $this->description(),
    'category'    => $this->category(),
    'version'     => $this->version(),
    'author'      => $this->author(),
    'license'     => $this->license(),
    'screenshots' => $this->screenshots(),
    'keywords'    => $this->keywords(),
    'presets'     => $this->presets(),
]
```

The widget browser UI calls `manifest()` (via `WidgetRegistry::manifests()`) once per widget instead of eight getters. The key set is stable — third-party widgets will depend on it.

### Preset shape

Each preset is an array with these keys:

```php
[
    'handle'            => 'dark-hero',        // slug, unique within the widget
    'label'             => 'Dark Hero',        // human-readable, non-empty
    'description'       => 'Short sentence.',  // string or null
    'config'            => [...],              // widget config overrides (subset of schema keys)
    'appearance_config' => [...],              // appearance jsonb bag subset
]
```

Every key in `preset.config` must appear in the widget's `schema()` **and** the corresponding schema field must declare `group: 'appearance'`. Presets may only shape the appearance layer — they are forbidden from touching content-group keys. CI enforces both rules.

### Inspector Presets tab

The inspector panel exposes presets as a third tab ("Presets") alongside Content and Appearance. When a widget is selected, the tab lists every preset the widget declares as a full-panel-width card — preset `label` on top, `description` below in muted text, and a reserved empty thumbnail slot above the text (see the path note below).

A synthetic "Blank" card is always prepended to the gallery. It represents the appearance-group subset of the widget's `defaults()` plus an empty `appearance_config`, giving the editor a one-click "reset appearance to defaults" starting point. The Blank card is rendered by the frontend only — it is not declared in `presets()` and does not appear in the manifest output.

Applying a preset is **appearance-only** with mixed apply semantics:

- `preset.config` is **overlaid** onto the widget instance's existing `config`. Only the appearance-group keys the preset declares change; every content-group key (the rich-text body, media IDs, CTA buttons, etc.) is preserved untouched.
- `preset.appearance_config` **replaces** the widget instance's `appearance_config` wholesale — the nested jsonb bag is 100 % appearance, so there is nothing to preserve.

The action routes through the same debounced save path the rest of the inspector uses, so the preview refreshes the same way a field edit does. The editor does not track which preset was applied — after apply, the widget is just a widget with that config, and subsequent field edits do not "dirty" or "detach from" the preset.

### User-authored drafts

Designers can save the current live appearance of a widget instance as a **draft preset** from inside the inspector Presets tab. Drafts live in the `widget_presets` table (see [`docs/schema/widget_presets.md`](schema/widget_presets.md)) — not on the widget definition class. They are a scratch pool scoped by `widget_type_id`, global per install (no per-site, per-template, or per-user ownership), and subject to the same appearance-only rule as code-authored presets: `config` keys that don't resolve to a `group: 'appearance'` schema field are rejected on save (422).

The save action snapshots the selected instance's current appearance — the resulting `preset.config` is a **fat slice**: every appearance-group schema key is materialized, either from the instance's own `config` or (for keys the designer never touched) from the widget type's resolved defaults. Presets are fat by design even though widget instances are sparse, because apply overlays `preset.config` onto the target; missing keys would otherwise inherit whatever the target already had set, producing a partial look. `appearance_config` is captured as-is. A new row gets an auto-generated `handle` (`draft-N`) and `label` (`Draft N`) — the designer renames later. The instance itself is not mutated.

The Presets tab merges code presets and DB drafts — Blank first, then `presets()`, then drafts (each carrying a small **Draft** badge). Applying a draft is identical to applying a code-authored preset: `config` overlayed, `appearance_config` replaced, routed through the debounced save path.

Each draft card exposes three actions:

- **Rename** — inline edit of `label` and `description`; server re-validates handle uniqueness on commit.
- **Delete** — removes the row.
- **Export** — writes a pretty-printed PHP array literal for the preset (`handle`, `label`, `description`, `config`, `appearance_config`) to the clipboard. The literal is paste-ready — it includes a trailing comma so it drops straight into a `presets(): array` return list.

Promotion to code is **manual**: export, paste into the widget's `presets()` method, run `php artisan test --filter=WidgetManifestTest` to confirm the literal is valid, then delete the draft from the gallery. There is no automated promotion path and no "promote draft to code" CLI — the code path is the canonical authoring surface.

CRUD happens only inside the builder inspector. There is no Filament resource for `widget_presets`, no admin list page, no bulk export. All four endpoints gate on the `update_page` permission.

### Preset thumbnails

Each widget's `thumbnails/` folder (session 174) also holds per-preset imagery:

```
app/Widgets/{PascalName}/thumbnails/preset-{handle}.png
```

Capture flow (host-side, not inside Docker):

1. The dev route `GET /dev/widgets/{handle}/presets/{presetHandle}` renders the widget at 800×500 with the preset applied. Baseline demo config comes from `defaults() + demoConfig()` (and any seeded collection handle); then `preset.config` is overlaid and `preset.appearance_config` replaces the demo appearance wholesale — matching the inspector's apply semantics.
2. `scripts/generate-thumbnails.js` iterates each widget's code-authored presets from the manifest, hits the dev preset route, and writes `preset-{handle}.png` alongside `static.png`. Run locally with `node scripts/generate-thumbnails.js --widget={handle}`; scope to one preset with `--preset={presetHandle}`.
3. Captured PNGs are committed to the repo; capture is a local developer chore, not a CI step.

The inspector's code-authored preset cards display the PNG when it exists on disk and fall back to the empty placeholder otherwise. Thumbnails are served by a dedicated public controller — see the "Dev tooling — widget thumbnails" block in `docs/app-reference.md` for the full route table.

**DB draft presets do not get thumbnails.** They change too fast to be worth a committed artefact; draft cards in the inspector keep the empty placeholder.

### CI validation

`tests/Feature/WidgetManifestTest.php` iterates every registered definition and asserts:

1. `version()` matches `/^\d+\.\d+\.\d+$/`.
2. `license()` is in the allow-list.
3. Every path in `screenshots()` exists on disk (resolved against `app/Widgets/{Folder}/`).
4. Every keyword matches `/^[a-z0-9-]+$/`.
5. Every preset has the correct shape and all `preset.config` keys exist in `schema()` under a field whose `group` is `appearance`.
6. `manifest()` returns exactly the expected top-level keys (prevents shape drift).

Each rule fails with a message naming the offending widget handle.

---

## Demo data & thumbnails

Widget sovereignty extends to demo data. Each widget that needs seeded fixtures for a functional preview ships its own `DemoSeeder` inside its folder — no central demo-data service, no parallel manifest.

### `demoSeeder()` contract

Every `WidgetDefinition` may optionally declare a demo seeder class via:

```php
public function demoSeeder(): ?string
{
    return DemoSeeder::class;
}
```

The base-class default returns `null`. When non-null, the FQCN must be an `Illuminate\Database\Seeder` subclass living at `app/Widgets/{PascalName}/DemoSeeder.php`. Seeders must be idempotent — running them on an already-seeded DB must not duplicate rows or error out.

As of session 174, six widgets declare seeders: `carousel`, `bar_chart`, `logo_garden`, `board_members`, `event_calendar`, `donation_form`. `ProductCarousel` and `ProductDisplay` intentionally do **not** declare one — with zero products, they render their own disconnected state, which is the correct signal.

### `demoConfig()` and `demoAppearanceConfig()`

Some widgets are config-only (no collection, no DB fixtures) — `text_block`, `hero`, `video_embed`. Their defaults are empty strings, so a defaults-only demo renders blank. Two additional optional methods on `WidgetDefinition` cover that path:

| Method | Default | Purpose |
|--------|---------|---------|
| `demoConfig(): array` | `[]` | Config overrides to apply for the demo render. Merged on top of `defaults()` by the dev controller. Keys must exist in `schema()`. |
| `demoAppearanceConfig(): array` | `[]` | Appearance-config overrides (padding, background gradient, text color, etc.) composed through `AppearanceStyleComposer`. Shape mirrors the live `appearance_config` jsonb bag. |

Both are optional, both live on the definition class, and both can coexist with `demoSeeder()` on the same widget. The dev controller computes the effective config as `defaults() + demoConfig() + (collection_handle override when a seeder ran)` and the effective appearance as `demoAppearanceConfig()`. The rendered widget is then wrapped in a single `<div>` carrying the composed inline style from `AppearanceStyleComposer`.

Neither method affects production rendering — they are only read by `App\Http\Controllers\Dev\WidgetDemoController`.

### Dev demo route

`GET /dev/widgets/{handle}` renders a single widget at a fixed 800×500 viewport using the production widget asset bundle. The route is registered from `routes/dev.php`, which `routes/web.php` only requires when `! App::environment('production')`. `App\Http\Middleware\DevRoutesMiddleware` enforces the same gate at request time.

The controller (`App\Http\Controllers\Dev\WidgetDemoController`) resolves the definition through `WidgetRegistry`, runs its `demoSeeder()` if declared (idempotent), builds a transient `PageWidget` with `defaults()` as config (plus the seeded `collection_handle` when the widget has a collection slot), and renders via `WidgetRenderer::render()`. There is no duplicate demo template — the dev route and the public site use the same pipeline.

### Static thumbnails (host-side Playwright)

Static PNG thumbnails are captured by `scripts/generate-thumbnails.js`, a standalone Node script that runs on the **host** (not inside Docker). Chromium is not installed in the app container.

Host-level install (once):

```bash
npm install --global playwright
npx playwright install chromium
```

Run against one widget or all:

```bash
node scripts/generate-thumbnails.js --widget=text_block
node scripts/generate-thumbnails.js --all
node scripts/generate-thumbnails.js --base-url=http://localhost
```

The script reads the widget list via `docker compose exec app php artisan widgets:manifest-json` and writes each PNG to `app/Widgets/{PascalName}/thumbnails/static.png`. Thumbnails are committed to the repo as part of the widget package — they are not build artifacts.

Animated thumbnails (MP4/WebP) and the ffmpeg pipeline are deferred to Stage 4.5 Phase 2.

---

## Current status (session 174)

Stage 4.5 Phase 1 of the Sovereign Widget arc is complete. Each widget that needs demo data now ships its own `DemoSeeder`; the dev demo route renders widgets in isolation at a fixed viewport; a host-side Playwright script produces static PNG thumbnails from real renders. Animated capture (Phase 2) is the next step.

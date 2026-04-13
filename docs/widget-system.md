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

Every key in `preset.config` must appear in the widget's `schema()` — CI enforces this.

### CI validation

`tests/Feature/WidgetManifestTest.php` iterates every registered definition and asserts:

1. `version()` matches `/^\d+\.\d+\.\d+$/`.
2. `license()` is in the allow-list.
3. Every path in `screenshots()` exists on disk (resolved against `app/Widgets/{Folder}/`).
4. Every keyword matches `/^[a-z0-9-]+$/`.
5. Every preset has the correct shape and all `preset.config` keys exist in `schema()`.
6. `manifest()` returns exactly the expected top-level keys (prevents shape drift).

Each rule fails with a message naming the offending widget handle.

---

## Current status (session 173)

Stage 4 of the Sovereign Widget arc is complete. Every registered widget — all 30 (29 seeder-declared widgets plus `nav`) — is now a self-contained folder under `app/Widgets/`. The manifest/metadata contract is defined on the base class with CI validation in place; Stage 5 (widget browser UI) is the first consumer.

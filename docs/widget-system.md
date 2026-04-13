# Widget System

Part of the multi-session Sovereign Widget arc (sessions 170+). This document describes the code-level widget authoring pattern introduced in session 170.

---

## Overview

Historically, widget types were declared as arrays inside `database/seeders/WidgetTypeSeeder.php`. Starting in session 170, a widget can be declared as a PHP class — a **widget definition** — which the application registers at boot time and syncs into the `widget_types` table.

The database remains the runtime source of truth. The renderer, inspector, and widget picker all continue reading from `widget_types`. The definition class is a code-level authoring surface that writes to the DB via a sync step.

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
- `template(): string` — Blade template string

**Optional methods (defaulted):**

- `category()` → `['content']`
- `collections()` → `[]`
- `assets()` → `[]`
- `fullWidth()` → `false`
- `defaultOpen()` → `false`
- `allowedPageTypes()` → `null` (meaning "all")
- `renderMode()` → `'server'`
- `requiredConfig()` → `null`

**Introspection / validation:**

- `toRow()` — returns the array shape `WidgetType::updateOrCreate()` expects.
- `validate()` — throws when `defaults()` is missing a key declared in `schema()`.

### `App\Services\WidgetRegistry`

Singleton service. Holds registered definitions keyed by handle.

- `register(WidgetDefinition $def): void`
- `all(): array`
- `find(string $handle): ?WidgetDefinition`
- `sync(): void` — iterates all definitions, calls `validate()`, then `WidgetType::updateOrCreate(['handle' => $def->handle()], $def->toRow())`.

### `App\Providers\WidgetServiceProvider`

- `register()` registers the `WidgetRegistry` singleton.
- `boot()` registers concrete definitions: `$registry->register(new NavDefinition())`.

### Seeder integration

`WidgetTypeSeeder` still declares all widgets that have not yet been migrated to the definition pattern. At the end of `run()`, it calls `app(WidgetRegistry::class)->sync()` so that registry-declared widgets are written to the DB alongside the seeder-declared ones.

Order: seeder entries first, then registry sync. Since both use `updateOrCreate` keyed on handle, a handle collision would result in the registry version overwriting the seeder version.

---

## Adding a new widget under the new system

1. Create `app/Widgets/{Name}/{Name}Definition.php` extending `WidgetDefinition`.
2. Implement the required methods.
3. Register it in `WidgetServiceProvider::boot()`: `$registry->register(new \App\Widgets\{Name}\{Name}Definition());`
4. Remove (or skip adding) a corresponding `WidgetType::updateOrCreate(...)` block in `WidgetTypeSeeder`.
5. Run `php artisan migrate:fresh --seed` (or `db:seed`) to write the row.

---

## Config resolution (session 171)

Session 171 introduced `App\Services\WidgetConfigResolver`, a stateless singleton that composes a widget's effective config at read time. This replaces per-template `$config['x'] ?? 'fallback'` defensiveness.

### Composition order

For any `PageWidget`, the resolver produces:

```
defaults → theme overrides (stub) → instance config
```

Later layers overwrite earlier ones key-by-key.

- **defaults:** from `WidgetDefinition::defaults()` if the widget handle is registered, otherwise from `WidgetType::getDefaultConfig()` (which reads `config_schema[].default`). This is the coexistence branch for widgets not yet migrated to the definition pattern.
- **theme overrides:** a Stage-N extension point. In session 171 this returns `[]`. Future template/theme-level defaults will compose in here without any per-widget template change.
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

## Current status (session 171)

Only the `nav` widget has been migrated to the definition class. All other widgets remain declared in `WidgetTypeSeeder`. The coexistence path is permanent until every widget is migrated — both definition and seeder-sourced widgets flow through the resolver identically.

Blade templates and SCSS partials for definition-backed widgets still live at their legacy paths (`resources/views/widgets/*.blade.php`, `resources/scss/widgets/_*.scss`). Physical file colocation into `app/Widgets/*/` is deferred to a later stage of the Sovereign Widget arc.

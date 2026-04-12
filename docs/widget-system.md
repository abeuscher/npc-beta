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

## Current status (session 170)

Only the `nav` widget has been migrated. All other widgets remain declared in `WidgetTypeSeeder`. The coexistence path is permanent until every widget is migrated.

Blade templates and SCSS partials for definition-backed widgets still live at their legacy paths (`resources/views/widgets/*.blade.php`, `resources/scss/widgets/_*.scss`). Physical file colocation into `app/Widgets/*/` is deferred to a later stage of the Sovereign Widget arc.

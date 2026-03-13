# Decision 013 — Widget System Architecture

*Recorded: March 2026 (Session 007)*

---

## Context

Pages needed a way to display dynamic content components — blog listings, collection data, rich text blocks — beyond a single rich text field. The system had to be:

- Admin-configurable (placement, options, order) without developer involvement
- Developer-extensible (new widget types as PHP classes, not DB config)
- Connected to the existing Collections data layer

---

## Decision: Widget Types as PHP Classes, Widget Instances as Database Records

Widget types live in `app/Widgets/`, each extending the abstract `App\Widgets\Widget` base. Widget instances are stored in `page_widgets` and reference a type by handle. Admins configure instances; developers define types.

This separates **what a widget can do** (PHP, version-controlled) from **how it's placed and configured** (database, admin-controlled).

---

## Widget Type Contract

Every widget type implements:

| Method | Purpose |
|--------|---------|
| `handle(): string` | Short string key used in DB and registry (`collection_list`) |
| `label(): string` | Human-readable name shown in admin UI |
| `configSchema(): array` | Filament form components for admin configuration |
| `resolveData(array $config): array` | Fetch and return data to pass to the view |
| `view(): string` | Blade view name (`widgets.collection-list`) |

---

## WidgetRegistry

`App\Widgets\WidgetRegistry` is a static registry (no DI container needed at this scale). Widget types are registered in `AppServiceProvider::boot()`:

```php
WidgetRegistry::register([
    CollectionListWidget::class,
    BlogRollWidget::class,
    RichTextWidget::class,
]);
```

The registry provides:
- `get(handle)` — returns a widget instance or null
- `getClass(handle)` — returns the class string or null
- `options()` — returns `['handle' => 'Label']` map for Filament selects

Adding a new widget type requires: create the class in `app/Widgets/`, add it to `AppServiceProvider`, create its Blade view.

---

## Data Resolution

`App\Services\WidgetDataResolver` routes a collection handle to the right data source:

- `source_type = custom` → queries `CollectionItem` records (JSONB data)
- `source_type = blog_posts` → queries `Post` model (published, ordered by date)
- `source_type = events` → placeholder, returns empty array (Event model deferred)

This keeps widget data fetching consistent regardless of whether a collection is backed by flexible JSONB items or a real Eloquent model.

---

## Page Integration

Widgets are attached to pages via the `page_widgets` table. The `PageController` loads active widgets in sort order, resolves each type instance, calls `resolveData()`, and passes the result stack to the view:

```php
$widgets = $page->pageWidgets()
    ->where('is_active', true)
    ->orderBy('sort_order')
    ->get()
    ->map(fn ($pw) => [
        'instance' => $pw->typeInstance(),
        'config'   => $pw->config,
        'data'     => $pw->typeInstance()?->resolveData($pw->config) ?? [],
    ]);
```

The `<x-page-widgets :widgets="$widgets" />` component iterates the stack and `@include`s each widget's view.

---

## What This Does Not Cover (Future Extension Points)

- **Layout zones** — widgets currently go on pages only. Global sidebar, footer, and reusable layout areas would require a `widget_zones` concept instead of `page_id`.
- **Caching** — widget data is resolved on every page request. A simple cache layer keyed by `page_id + sort_order + updated_at` is the natural next step.
- **Per-widget Alpine.js interactivity** — views are plain Blade. Adding Alpine components to specific widget types is opt-in.
- **Widget versioning** — no revision history for widget instances.
- **Events widget** — `WidgetDataResolver` has a placeholder branch for `source_type = events`; it returns an empty array until the `Event` model exists.

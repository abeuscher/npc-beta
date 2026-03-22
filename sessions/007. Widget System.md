# Session 007 Prompt — Widget System

## Context

Session 006 built the Collections data layer: generic JSONB-backed collections and system collections (`blog_posts`, `events`) backed by real Eloquent models. The `Collection::scopePublic()` scope and `handle` identifier are the entry points the widget system will consume.

Session 007 builds the widget system: the layer that places content components on public-facing pages, connects them to collection data, and renders them on the frontend.

---

## What a Widget Is

A **widget** is a configurable content component placed on a page by an admin. It has:

1. **A type** — defined in PHP code by a developer. The type specifies what data it needs, what config options are available, and which Blade view renders it.
2. **An instance** — stored in the database. Belongs to a page. Carries a `config` JSONB object with admin-chosen values (which collection to pull from, how many items to show, a heading, etc.).
3. **A rendered output** — the Blade view receives the resolved data and config, and outputs HTML for the public frontend.

Widgets are **not** generic CMS blocks. They are typed components. A "Blog Roll" widget knows it talks to the `blog_posts` collection source. A "Collection List" widget knows it takes a `collection_handle` parameter and queries that collection's items. A "Rich Text" widget has no data source and just renders configured HTML content.

---

## Architecture Decisions

### Widget types = PHP classes; widget instances = database records

Widget types live in `app/Widgets/`. Each is a class extending `App\Widgets\Widget` (abstract base). The admin picks a type when creating a widget instance; the instance stores config for that type. This gives developers full control over widget behaviour while giving admins control over placement and configuration.

### Widget instances belong to Pages (for now)

A `page_widgets` table associates widget instances to pages. Extending to global sidebar areas, footers, or reusable layout zones is future work. For this session: widgets go on pages.

### Page content + widgets coexist

A page can have both a `content` rich text field and widgets. The public template renders content first (if present), then the widget stack below, ordered by `sort_order`. More sophisticated layout control is future work.

### Data resolution via a service class

`App\Services\WidgetDataResolver` takes a collection handle and config, resolves the correct data source (custom `collection_items` JSONB or a model like `Post`), applies filters (limit, order), and returns a data array. Widget types call this service in their `resolveData()` method.

---

## Data Model

### `page_widgets` table

Migration `create_page_widgets_table`:
- `id` — UUID primary
- `page_id` — UUID FK → pages, cascade delete
- `widget_type` — string — fully-qualified class name or short handle (e.g. `collection_list`)
- `label` — string, nullable — admin-facing label for identifying the widget in the list
- `config` — JSONB — widget-specific configuration (collection_handle, limit, heading, etc.)
- `sort_order` — integer, default 0
- `is_active` — boolean, default true
- timestamps

No soft deletes — widget instances are lightweight and owned by their page.

---

## Abstract Widget Base Class

`app/Widgets/Widget.php`:

```php
abstract class Widget
{
    // Short handle used to register and reference this widget type.
    abstract public static function handle(): string;

    // Human-readable name shown in the admin type selector.
    abstract public static function label(): string;

    // Filament form schema for the config fields specific to this widget type.
    // Fields should be keyed under the config array (e.g. TextInput::make('config.heading')).
    abstract public static function configSchema(): array;

    // Resolves and returns the data array this widget will pass to its view.
    abstract public function resolveData(array $config): array;

    // Blade view name for rendering on the public frontend.
    abstract public function view(): string;
}
```

---

## Widget Registry

`app/Widgets/WidgetRegistry.php` — a singleton (or bound in AppServiceProvider) that holds a map of `handle => class`. Widget types register themselves in `AppServiceProvider::boot()`:

```php
WidgetRegistry::register([
    \App\Widgets\CollectionListWidget::class,
    \App\Widgets\BlogRollWidget::class,
    \App\Widgets\RichTextWidget::class,
]);
```

The registry is used by the admin form to populate the widget type selector and by the public frontend to resolve a type from a stored handle.

---

## Starter Widget Types

### `CollectionListWidget` (handle: `collection_list`)

Config fields:
- `collection_handle` — Select populated from `Collection::public()->pluck('name', 'handle')`
- `heading` — TextInput, nullable
- `limit` — TextInput, numeric, nullable (no limit if blank)

`resolveData()`: calls `WidgetDataResolver::resolve($config['collection_handle'], limit: $config['limit'])` — returns array of item data arrays.

View: `resources/views/widgets/collection-list.blade.php`

### `BlogRollWidget` (handle: `blog_roll`)

Config fields:
- `heading` — TextInput, nullable
- `limit` — TextInput, numeric, default 5
- `show_excerpt` — Toggle, default true

`resolveData()`: queries `Post` model for published posts ordered by `published_at` desc, limited, returns array of post data.

View: `resources/views/widgets/blog-roll.blade.php`

### `RichTextWidget` (handle: `rich_text`)

Config fields:
- `content` — RichEditor

`resolveData()`: returns `['content' => $config['content']]` — no collection query.

View: `resources/views/widgets/rich-text.blade.php`

---

## WidgetDataResolver Service

`app/Services/WidgetDataResolver.php`

```php
public static function resolve(string $handle, ?int $limit = null, string $orderBy = 'sort_order', string $direction = 'asc'): array
```

Resolution logic:
1. Find `Collection::where('handle', $handle)->public()->firstOrFail()`
2. Branch on `source_type`:
   - `custom` → query `CollectionItem` where `collection_id` + `is_published = true`, apply limit/order, return `->pluck('data')->all()`
   - `blog_posts` → query `Post` where `is_published = true`, ordered by `published_at desc`, apply limit, return mapped arrays
   - `events` → placeholder: return empty array with a comment noting this resolves when Event model exists
3. Returns `array` always — widget views can handle empty gracefully

---

## Admin UI

### PageWidgetsRelationManager

`app/Filament/Resources/PageResource/RelationManagers/PageWidgetsRelationManager.php`

Relationship: `pageWidgets`

**Table columns:**
- label — "Widget" column, falls back to widget type label if no label set
- widget_type — formatted as the type's `label()` value
- is_active — boolean icon
- sort_order — sortable

`reorderable('sort_order')`

**Form:**

Step 1 in the form — widget type selector:
```
Select::make('widget_type')
    ->label('Widget Type')
    ->options(WidgetRegistry::options()) // ['collection_list' => 'Collection List', ...]
    ->required()
    ->live()
```

Step 2 — config fields rendered dynamically based on selected type:
```
Section::make('Configuration')
    ->schema(fn (Get $get) => WidgetRegistry::get($get('widget_type'))?->configSchema() ?? [])
    ->visible(fn (Get $get) => filled($get('widget_type')))
```

Plus fixed fields:
- `label` — TextInput, nullable, helperText "Optional admin label for identifying this widget."
- `sort_order` — TextInput, numeric, default 0
- `is_active` — Toggle, default true

### PageResource update

Add `PageWidgetsRelationManager::class` to `PageResource::getRelationManagers()`.

Add `hasMany(PageWidget::class)` to `Page` model.

---

## Models

**`app/Models/PageWidget.php`**: `HasUuids`, `HasFactory`, `belongsTo(Page::class)`. Cast `config` as `array`.

Add helper `typeInstance(): ?Widget` that resolves the widget class from the registry and returns an instance, or null if the type isn't registered.

**`app/Models/Page.php`**: add `hasMany(PageWidget::class)` relationship.

---

## Public Frontend

### Page controller update

Load active, ordered widgets for the page:
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

Pass `$widgets` to the view.

### Blade component

`resources/views/components/page-widgets.blade.php`:
```blade
@foreach ($widgets as $widget)
    @if ($widget['instance'])
        @include($widget['instance']->view(), [
            'config' => $widget['config'],
            'data'   => $widget['data'],
        ])
    @endif
@endforeach
```

Call `<x-page-widgets :widgets="$widgets" />` in the page layout after the page content block.

### Widget views

`resources/views/widgets/collection-list.blade.php`:
- Renders `$config['heading']` if set
- Loops `$data` and outputs each item — since schema varies by collection, output all key/value pairs in a simple `<dl>` for now. Session-specific widget views that know their schema are future work.

`resources/views/widgets/blog-roll.blade.php`:
- Renders `$config['heading']` if set
- Loops posts: title (linked to blog URL using `blog_prefix` site setting + slug), excerpt if `show_excerpt` is true, published date

`resources/views/widgets/rich-text.blade.php`:
- Renders `{!! $data['content'] !!}`

---

## Tests

- `WidgetDataResolverTest`:
  - Returns empty array for a non-public collection handle
  - Returns published collection items for a valid public custom collection
  - Returns published posts for the `blog_posts` handle
  - Respects the `limit` parameter
- `PageWidgetTest`:
  - A PageWidget can be created and associated with a page
  - `typeInstance()` returns the correct widget class
  - Inactive widgets are excluded when the page controller loads widgets

---

## Documentation

- `docs/decisions/013-widget-system-architecture.md`: documents the type/instance split, why widgets are typed PHP classes, the data resolver pattern, and the extension points (new widget types, future layout zones)
- Update `docs/information-architecture.md`: add PageWidget to Content domain
- `sessions/session-007-log.md`: written at session end

---

## What This Session Does Not Cover

- Widget layout zones (sidebars, footers, global areas)
- Widget caching / performance optimisation
- Per-widget Alpine.js interactivity beyond what's in existing frontend setup
- Event widget (placeholder only — resolves when Event model exists)
- Widget versioning or revision history

---

## Acceptance Criteria

- [ ] Widget types are registered via `WidgetRegistry` in `AppServiceProvider`
- [ ] `CollectionListWidget`, `BlogRollWidget`, and `RichTextWidget` are implemented
- [ ] `WidgetDataResolver` routes `blog_posts` handle to Post model and `custom` handles to CollectionItem
- [ ] Admin can add, configure, reorder, and deactivate widgets on a page via the relation manager
- [ ] Widget type selector dynamically renders the correct config form fields
- [ ] Public page template renders active widgets below page content, in sort order
- [ ] Blog roll widget links to posts using the `blog_prefix` site setting
- [ ] Collection list widget renders empty gracefully when no published items exist
- [ ] `php artisan test` passes

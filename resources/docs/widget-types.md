---
title: Widget Types
description: Developer-managed widget type definitions — configuring server-rendered and client-rendered widgets available in the page builder.
version: "0.86"
updated: 2026-03-28
tags: [admin, cms, widgets, developer]
routes:
  - filament.admin.resources.widget-types.index
  - filament.admin.resources.widget-types.create
  - filament.admin.resources.widget-types.edit
---

# Widget Types

Widget Types defines the reusable building blocks available in the page builder. Each widget type is a template with a unique handle, a render mode, optional configuration fields, rendering code, and scoped CSS. When a widget is placed on a page, an instance is created from the type definition.

This is a developer-facing tool. Poorly written render code can break public pages. Access requires the `view_any_widget_type` permission.

Some built-in widget types are **pinned** — they cannot be deleted. Pinned widgets are part of the system's core functionality.

## Basic Fields

- **Label** — the human-readable name shown in the page builder widget picker.
- **Handle** — a unique machine-readable identifier, auto-generated from the label on creation. Cannot be changed after creation. Used in CSS scoping (`.widget--{handle}`) and in templates.
- **Render mode** — how the widget is rendered: **Server** (Blade template, evaluated at request time) or **Client** (arbitrary JavaScript, evaluated in the browser).
- **Collections** — legacy field, no longer used for data injection. Retained on existing records for reference but has no effect on rendering. Widget data access is now handled through `$pageContext` (see below).

## Config Fields

Config fields are per-instance configuration options that page editors can set when placing the widget on a page. For example, a "Hero" widget might have a config field for the heading text, or an event widget might have a field for the event slug.

Each field has a key, label, and type (`text`, `textarea`, `richtext`, `url`, `number`, `toggle`). In the render template, all config values are available as the `$config` associative array — for example, `$config['heading']` or `$config['event_slug']`.

Config values are always strings. Cast to the appropriate type before use:

```php
$limit = isset($config['limit']) && $config['limit'] !== '' ? (int) $config['limit'] : null;
```

## Server Mode

When render mode is **Server**, the widget is rendered by a Blade template on the server at request time.

Every server-mode template receives two variables:

- **`$config`** — associative array of this widget instance's config field values (see above).
- **`$pageContext`** — a `PageContext` service object shared across all widgets on the current page (see `$pageContext` Reference below).

Templates are rendered via `Blade::render()` and typically delegate to a view file using `@include`:

```
@include('widgets.my-widget')
```

The **JavaScript** field contains optional JavaScript that runs when the widget is rendered on the page.

## Client Mode

When render mode is **Client**, the widget renders entirely in the browser via JavaScript.

- **Code** — arbitrary JavaScript that runs in the browser with full DOM access. Use with care and only with trusted code.

Client-mode widgets receive `$config` in PHP scope during rendering but the render produces only a `<script>` block — no server-side HTML. `$pageContext` is available if needed during rendering.

## CSS

The **CSS** field accepts scoped styles for this widget type. Styles are injected into the page inline alongside the widget's rendered HTML.

---

## `$pageContext` Reference

`$pageContext` is a `App\Services\PageContext` instance available in every server-mode widget template on the current request. It provides typed, lazy, memoized access to page-level data. Queries run only on first access and are cached for the remainder of the request — calling `$pageContext->posts()` from two different widgets on the same page costs one query, not two.

### Properties

| Property | Type | Description |
|---|---|---|
| `currentPage` | `?Page` | The `Page` model currently being rendered. `null` outside a page render context. |
| `currentUser` | `?PortalUser` | The authenticated portal (member) user, or `null` if not logged in. Never an admin user. |

### Stream Methods

Stream methods return collections of records. All accept an optional `$limit` parameter (integer or null).

| Method | Returns | Description |
|---|---|---|
| `posts(?int $limit)` | `Collection<Page>` | Published pages of type `post`, ordered by `COALESCE(published_at, created_at) DESC`. |
| `pages(?int $limit)` | `Collection<Page>` | Published pages of all types except `post`, ordered by `title ASC`. Useful for nav and search widgets. |
| `upcomingEvents(?int $limit)` | `Collection<Event>` | Published events where `starts_at >= now()`, ordered ascending. Returns full Eloquent models. |
| `collection(string $handle, ?int $limit)` | `array` | Resolves a named collection by handle via `WidgetDataResolver`. Returns an array of data arrays. Returns `[]` for unknown or non-public handles. See System Collections table below. |

### Record Methods

Record methods return a single model by key, cached per key for the remainder of the request.

| Method | Returns | Description |
|---|---|---|
| `event(string $slug)` | `?Event` | Published event with the given slug, or `null`. |
| `product(string $slug)` | `?Product` | Published product with the given slug, or `null`. Eager-loads `prices`. |
| `form(string $handle)` | `?Form` | Active form with the given handle, or `null`. |

### Usage pattern

```blade
{{-- Assign from config, then use --}}
@php $event = $pageContext->event($config['event_slug'] ?? null); @endphp
@isset($event)
    <h2>{{ $event->title }}</h2>
@endisset

{{-- Stream with limit from config --}}
@php
    $limit = isset($config['limit']) && $config['limit'] !== '' ? (int) $config['limit'] : null;
    $posts = $pageContext->posts($limit);
@endphp
```

---

## System Collections

The following handles are built in and resolved by `WidgetDataResolver`. Use them with `$pageContext->collection($handle)`.

| Handle | Source | Data keys returned |
|---|---|---|
| `blog_posts` | Published pages of type `post` | `id`, `title`, `slug`, `published_at` |
| `events` | Published upcoming events | `id`, `title`, `slug`, `starts_at`, `ends_at`, `is_virtual`, `is_free`, `url` |
| `products` | Published products | `id`, `name`, `slug`, `description`, `capacity`, `available` |

Custom collections defined in the Collections admin are also resolvable by their handle, provided they are marked public and active.

Note: `blog_posts` and `events` are also accessible as typed Eloquent collections via `$pageContext->posts()` and `$pageContext->upcomingEvents()` respectively. Prefer those methods when you need model properties or relationships; use `collection()` when you need the flat array format.

---

## Column Layout Widget

The `column_widget` type creates a CSS grid container that can hold other widgets in named column slots. It is the primary tool for building multi-column page layouts.

### Purpose and configuration

A column widget instance carries two config fields:

| Key | Type | Description |
|---|---|---|
| `num_columns` | number | How many column slot panels to display in the page builder and how many `<div>` columns to render on the front end |
| `grid_template_columns` | text | A valid CSS `grid-template-columns` value, e.g. `1fr 1fr` or `2fr 1fr` |

The template renders a `<div style="display:grid; grid-template-columns:...">` containing one `<div>` per column (0-indexed). Each column `<div>` receives the rendered HTML of any child widgets assigned to that slot.

### Grid presets

The page builder exposes four one-click presets that write a standard `grid_template_columns` value:

| Preset label | Value written |
|---|---|
| Equal halves | `1fr 1fr` |
| Equal thirds | `1fr 1fr 1fr` |
| 2/3 + 1/3 | `2fr 1fr` |
| 1/3 + 2/3 | `1fr 2fr` |

Selecting a preset writes to the `grid_template_columns` text input. The input can always be overridden manually for custom values.

### Assigning children to columns

Child widgets are `page_widgets` rows with `parent_widget_id` set to the column widget's id and `column_index` set to the zero-based column number. The column widget template groups its active children by `column_index` and renders each group into the corresponding grid column.

Children are ordered within their slot by `sort_order`. Up/down buttons in the page builder reorder children within the same slot (same `parent_widget_id` + `column_index`). Drag-and-drop reordering within slots is not supported.

### Adding blocks to a column slot

In the page builder, each column slot panel has its own **+ Add Block** button. Clicking it opens a widget picker modal scoped to that slot. Creating a block from that modal writes `parent_widget_id` and `column_index` on the new `page_widgets` row.

---

## `parent_widget_id` / `column_index` schema and nesting model

`page_widgets` supports self-referential nesting via two columns:

| Column | Type | Description |
|---|---|---|
| `parent_widget_id` | uuid nullable | FK → `page_widgets.id`, set null on parent delete |
| `column_index` | smallint unsigned nullable | Zero-based slot index within the parent column widget; null for root widgets |

Root-level widgets have `parent_widget_id = null`. The rendering pipeline and the page builder load only root-level widgets at the top level and eager-load children from there.

**Nesting depth:** unlimited. There is no depth limit in the code or the UI. A column widget inside a column slot can itself contain a column widget.

**Deleting a column widget:** the `onDelete('set null')` foreign key constraint sets `parent_widget_id` to null on all direct children. Those children become orphaned root-level widgets on their page rather than being deleted. They will appear at the top of the page builder block list and can be deleted or moved manually.

---

## `style_config` — universal spacing layer

Every `page_widgets` row has a `style_config` jsonb column (default `{}`). It holds per-instance padding and margin values applied as inline CSS on the widget's wrapper `<div>` by the front-end renderer.

### Fields

| Key | CSS property applied |
|---|---|
| `padding_top` | `padding-top` |
| `padding_right` | `padding-right` |
| `padding_bottom` | `padding-bottom` |
| `padding_left` | `padding-left` |
| `margin_top` | `margin-top` |
| `margin_right` | `margin-right` |
| `margin_bottom` | `margin-bottom` |
| `margin_left` | `margin-left` |

Values are stored as strings (numeric pixel integers). The renderer casts each value to `int` before appending `px` — non-numeric or empty values are silently skipped. Raw string values from `style_config` are never emitted directly into the style attribute, preventing injection.

### How the renderer applies it

`resources/views/components/page-widgets.blade.php` iterates the `$blocks` array and builds an inline `style` attribute from the non-empty `style_config` values before rendering the wrapper div. The same logic is duplicated inside `resources/views/widgets/column-widget.blade.php` for child widgets rendered within column slots.

### Availability

`style_config` is available on all widget types, not just column widgets. The advanced panel is accessible on every block in the page builder.

---

## Advanced controls panel

Every block in the page builder has an **advanced panel** for spacing controls. It is hidden by default and toggled by the sliders icon button in the block header (to the left of the ellipsis menu).

The panel contains:

- **Padding (px):** an "All sides" shorthand input at the top, plus four individual Top/Right/Bottom/Left inputs.
- **Margin (px):** same layout as padding.

**Shorthand behaviour:** when all four individual values for a property (padding or margin) are equal and non-empty, the shorthand input shows that value. When they differ, the shorthand input shows an empty field with placeholder `mixed`. Writing to the shorthand input sets all four individual values at once. Individual inputs remain independently editable after that.

Changes are persisted immediately via `wire:model.live` on the individual inputs. The shorthand inputs are Alpine-only and drive the Livewire model by proxy.

---

## Operational notes

- **Deleting a column widget** sets `parent_widget_id` to null on its children — they become orphaned root-level widgets rather than being deleted with the parent. Remove children manually before deleting the column widget if they are no longer needed.
- **Moving a child widget to root level via the UI** is not currently supported. To promote a child, delete the column widget (which orphans the children) or reassign `parent_widget_id` directly in the database.
- **Root-level drag-to-reorder** continues to work on collapsed column container blocks. Drag-and-drop within column slots is not supported — use the Up/Down buttons instead.

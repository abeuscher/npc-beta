---
title: Widget Types
description: Developer-managed widget type definitions — configuring server-rendered and client-rendered widgets available in the page builder.
version: "0.88"
updated: 2026-04-11
tags: [admin, cms, widgets, developer]
routes:
  - filament.admin.resources.widget-types.index
  - filament.admin.resources.widget-types.create
  - filament.admin.resources.widget-types.edit
category: cms
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

Each field has a key, label, and type. The supported types are `text`, `textarea`, `richtext`, `number`, `toggle`, `color`, `select`, `image`, `video`, `url`, and `buttons`. See the **Config Field Types** table in `widget-development.md` for the full per-type reference (props, storage shape, render notes). The `color` field type is rendered by the shared `ColorPicker` primitive — see the **Shared Appearance Primitives** section in `widget-development.md` for the full primitive API. In the render template, all config values are available as the `$config` associative array — for example, `$config['heading']` or `$config['event_slug']`.

### `select` field type

A `select` field renders a dropdown in the inspector panel populated from a named data source. The stored value is a string key (slug or handle) chosen from that source.

Field definition example:

```php
['key' => 'event_slug', 'type' => 'select', 'label' => 'Event', 'options_from' => 'events']
```

The `options_from` key names a built-in data source resolved by `App\Services\PageBuilderDataSources`. Available built-in sources:

| Source name | Records returned | Value key | Label key |
|---|---|---|---|
| `events` | Published events, ordered by `starts_at` | `slug` | `title` |
| `products` | Published products, ordered by `name` | `slug` | `name` |
| `forms` | Active forms, ordered by `title` | `handle` | `title` |

Existing saved config values (slugs/handles) stored as plain strings remain valid — no migration is needed when converting a field from `text` to `select`.

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

## Multi-column layouts

Multi-column page layouts are no longer built from a `column_widget` widget type. They are first-class `page_layouts` rows with their own schema, lifecycle, and inspector panel. A `page_widgets` row references its containing layout via `layout_id` (and slot via `column_index`); root-level widgets have `layout_id IS NULL`. See [docs/schema/page_widgets.md](../../docs/schema/page_widgets.md) and [docs/schema/page_layouts.md](../../docs/schema/page_layouts.md) for the current data model.

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

`resources/views/components/page-widgets.blade.php` iterates the `$blocks` array and builds an inline `style` attribute from the non-empty `style_config` padding/margin values before rendering the wrapper div. Each value is cast to `int` and suffixed with `px`; raw string values are never emitted directly. The editor preview (rendered by `App\Livewire\PageBuilder::buildInlineStyles()`) follows the same casting rules.

### Availability

`style_config` is available on all widget types. Spacing controls are accessible on every block via the inspector's Appearance tab.

---

## Inspector — Appearance tab and spacing controls

The page builder inspector is a Vue island; see the **Inspector** section in `widget-development.md` for the architecture. Within the inspector, the spacing controls live on the **Appearance** tab and are rendered by the Vue component `resources/js/page-builder-vue/components/SpacingControl.vue`.

The control surfaces:

- **Padding (px):** an "All sides" shorthand input plus four individual Top / Right / Bottom / Left inputs.
- **Margin (px):** same layout as padding.

**Shorthand behaviour:** when all four individual values for a property (padding or margin) are equal and non-empty, the shorthand input displays that value. When they differ, the shorthand input is empty with placeholder `mixed`. Writing to the shorthand input sets all four individual values at once. Individual inputs remain independently editable after that.

**Persistence:** changes mutate the local Pinia editor store immediately (so the inspector and preview update without a round-trip), then trigger a debounced `PUT /widgets/{id}` request to the page builder REST API after 350 ms of input idle time. There is no Livewire `wire:model.live` binding involved — the inspector is fully Vue/Pinia.

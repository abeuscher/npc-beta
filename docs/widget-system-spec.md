# Widget System Specification

Complete specification for the installable widget system. This document captures the full vision — individual sections note what is in scope for beta vs. post-beta.

---

## Principles

1. **Each widget is a self-contained folder.** All parts — manifest, template, styles, JS, collection definitions — live together. The folder is the source of truth; the database row is derived from it.
2. **One CSS bundle, one JS bundle.** Vite compiles all installed widget assets into two files. No inline `<style>` or `<script>` tags. No per-page sharding.
3. **Only installed widgets are bundled.** If a widget isn't installed (no DB row), its assets are not compiled. The `widget_types` table is the registry.
4. **Widgets are deliverable.** A widget folder can be its own git repo. Delivery to a client: clone into `widgets/`, run install command, build.
5. **Shared tools are not widgets.** Reusable config field types (buttons, gradient, etc.) live in a parallel `_fields/` directory and are available to all widgets.

---

## Directory structure

```
widgets/
    _fields/                          ← shared config field types
        buttons/
            field.json                ← name, data shape, dependencies
            inspector.blade.php       ← admin UI for the config panel
            render.blade.php          ← frontend rendering partial
        gradient/
            field.json
            inspector.blade.php
            render.blade.php

    hero/                             ← a widget
        widget.json                   ← manifest
        template.blade.php            ← server-rendered Blade template
        style.scss                    ← compiled into site CSS bundle
        script.js                     ← compiled into site JS bundle
        README.md                     ← widget documentation
        collections/                  ← optional collection definitions
            data.json

    carousel/
        widget.json
        template.blade.php
        style.scss
        script.js
        collections/
            slides.json

    text-block/
        widget.json
        template.blade.php
```

---

## Widget manifest (`widget.json`)

```json
{
    "handle": "hero",
    "label": "Hero",
    "description": "Full-width banner with background image, text overlay, and call-to-action buttons.",
    "category": ["content"],
    "allowed_page_types": null,
    "render_mode": "server",
    "full_width": true,
    "default_open": false,
    "config_schema": [
        {"key": "content", "type": "richtext", "label": "Content"},
        {"key": "background_image", "type": "image", "label": "Background Image"},
        {"key": "text_position", "type": "select", "label": "Text Position", "default": "center-center", "options": {
            "top-left": "Top Left",
            "top-center": "Top Center",
            "top-right": "Top Right",
            "center-left": "Center Left",
            "center-center": "Center",
            "center-right": "Center Right",
            "bottom-left": "Bottom Left",
            "bottom-center": "Bottom Center",
            "bottom-right": "Bottom Right"
        }},
        {"key": "ctas", "type": "buttons", "label": "Buttons"},
        {"key": "overlap_nav", "type": "toggle", "label": "Overlap Navigation", "default": false},
        {"key": "overlay_opacity", "type": "number", "label": "Overlay Opacity", "default": 50},
        {"key": "min_height", "type": "select", "label": "Minimum Height", "default": "24rem", "options": {
            "16rem": "Small (16rem)",
            "24rem": "Medium (24rem)",
            "32rem": "Large (32rem)",
            "40rem": "Extra Large (40rem)"
        }}
    ],
    "collections": [],
    "dependencies": {
        "npm": {},
        "fields": ["buttons"],
        "widgets": []
    }
}
```

### Manifest field reference

| Field | Type | Required | Notes |
|---|---|---|---|
| handle | string | yes | Unique identifier. Must match the folder name. |
| label | string | yes | Display name in the widget picker. |
| description | string | no | Short description shown in the widget picker. |
| category | string[] | yes | One or more of: content, layout, media, blog, events, forms, portal, giving_and_sales |
| allowed_page_types | string[] or null | no | Array of page types, or null for all. |
| render_mode | "server" or "client" | yes | Server = Blade, client = JS-rendered. |
| full_width | boolean | no | Default false. When true, widget renders without page content container. |
| default_open | boolean | no | Default false. Whether the widget panel is open by default in the builder. |
| config_schema | array | yes | Config field definitions (same shape as current DB column). |
| collections | string[] | no | Collection slot names this widget uses. |
| dependencies.npm | object | no | NPM package requirements (name → semver). |
| dependencies.fields | string[] | no | Required shared field types from `_fields/`. |
| dependencies.widgets | string[] | no | Other widgets this widget depends on. |

---

## Shared config field types (`_fields/`)

Each field type provides:

| File | Purpose |
|---|---|
| `field.json` | Name, expected data shape, any dependencies |
| `inspector.blade.php` | Admin panel UI (Alpine/Livewire). Rendered by the inspector field dispatcher. |
| `render.blade.php` | Frontend rendering partial. Widget templates `@include` this. |

### `field.json` schema

```json
{
    "type": "buttons",
    "label": "Buttons",
    "data_shape": "array of {text: string, url: string, style: string}",
    "default": [],
    "dependencies": {
        "npm": {}
    }
}
```

### Field type resolution

The inspector field dispatcher (`inspector-field.blade.php`) resolves field types in order:

1. Built-in types: text, richtext, textarea, toggle, number, url, select, image
2. `_fields/{type}/inspector.blade.php` if it exists
3. Falls back to text

Widget templates reference field rendering partials explicitly:
```blade
@include('widgets._fields.buttons.render', ['buttons' => $config['ctas']])
```

---

## Collection definitions (post-beta)

A widget that uses collections can ship a definition file:

```
widgets/carousel/
    collections/
        slides.json
```

### `slides.json` schema

```json
{
    "handle": "slides",
    "label": "Slides",
    "fields": [
        {"key": "title", "type": "text", "label": "Title"},
        {"key": "image", "type": "image", "label": "Image"},
        {"key": "caption", "type": "text", "label": "Caption"}
    ],
    "sample_data": [
        {"title": "Welcome", "image": "samples/slide1.jpg"},
        {"title": "About Us", "image": "samples/slide2.jpg"}
    ]
}
```

The install command registers the collection type with the collection manager and optionally seeds sample data. The collection definition in `widget.json` references the slot name, which maps to the JSON filename.

---

## Artisan commands

### `widget:install {handle}`

1. Reads `widgets/{handle}/widget.json`
2. Validates manifest and checks dependencies (required fields exist, required widgets installed)
3. Writes or updates the `widget_types` DB row from the manifest
4. Sets `template` column to `@include('widgets.{handle}.template')` (Blade resolves from the widgets directory)
5. Clears `css`, `js` columns (these are no longer used — Vite handles assets)
6. Reports what NPM dependencies need to be installed (if any)
7. Triggers asset rebuild (or advises user to run build)

### `widget:remove {handle}`

1. Checks if any `page_widgets` reference this widget type (warns, requires `--force`)
2. Removes the `widget_types` DB row
3. Does NOT delete the widget folder (that's the user's responsibility)
4. Triggers asset rebuild

### `widget:sync`

1. Scans all `widgets/*/widget.json` files
2. Installs any that aren't in the DB
3. Updates any whose manifest has changed
4. Optionally removes DB rows for widgets whose folders no longer exist (`--prune`)
5. Replaces the current `WidgetTypeSeeder` for ongoing maintenance

### `widget:list`

Lists all installed widgets with handle, label, and status (installed, not installed, outdated).

---

## Vite build integration

### Dynamic SCSS entry

A Vite plugin or entry file that globs installed widget styles:

```js
// resources/js/widget-loader.js (auto-generated or dynamic)
// This file is regenerated by widget:install / widget:remove / widget:sync

// Widget styles
import '../../widgets/hero/style.scss';
import '../../widgets/carousel/style.scss';
import '../../widgets/event-calendar/style.scss';

// Widget scripts
import '../../widgets/carousel/script.js';
import '../../widgets/event-calendar/script.js';
```

Alternative: a Vite plugin that reads the `widget_types` table or scans `widgets/*/` at build time and auto-imports. Either way, the output is one CSS file and one JS file.

### Build trigger

After `widget:install` or `widget:remove`, the asset loader file is regenerated and the user runs `npm run build`. On the deploy server, the deploy script handles this.

---

## Template resolution

Widget Blade templates live at `widgets/{handle}/template.blade.php`. Laravel's view finder needs to know about this path. Options:

1. **Add `widgets/` to the view paths** in `config/view.php` or via a service provider
2. **Symlink** each widget's template into `resources/views/widgets/` at install time
3. **Register a custom namespace**: `view()->addNamespace('widgets', base_path('widgets'))`

Option 3 is cleanest. Templates are referenced as:
```php
'template' => "@include('widgets::hero.template')"
```

Or the renderer can resolve directly:
```php
$html = view("widgets::{$handle}.template", $templateVars)->render();
```

The `_fields/` render partials follow the same pattern:
```blade
@include('widgets::_fields.buttons.render', ['buttons' => $config['ctas']])
```

---

## Pinned / required widgets

Some widgets are required on specific pages (e.g., `portal_login` on the login page). This constraint currently lives in `WidgetType::$requiredFor`. Under the installable model, this moves to the manifest:

```json
{
    "handle": "portal_login",
    "required_on": ["login"]
}
```

The install command registers this. The remove command refuses to uninstall a required widget unless `--force` is used.

---

## Widget thumbnails

Widget picker thumbnails currently use Spatie media attached to the `widget_types` model. Under the installable model, thumbnails live in the widget folder:

```
widgets/hero/
    widget.json
    template.blade.php
    thumbnail.png           ← picker thumbnail
    thumbnail-hover.png     ← optional hover state
```

The install command registers these as media on the widget type record.

---

## Scope: beta vs. post-beta

### Beta

- `widgets/` directory structure with manifests for all existing widgets
- Shared `_fields/` directory for buttons (and gradient if built by then)
- `widget:sync` artisan command (reads manifests, writes DB rows)
- Vite dynamic entry — one CSS bundle, one JS bundle, no inline styles/scripts
- View namespace registration for widget templates
- Migrate all existing widgets to the new structure
- Remove WidgetTypeSeeder, remove ScssPhp inline compilation

### Post-beta

- `widget:install` / `widget:remove` individual commands with dependency checking
- NPM dependency auto-merging from widget manifests
- Collection definitions with sample data
- Widget preview in the page builder editor
- Remote widget registry (install from URL or package name)
- Widget versioning and update mechanism
- Per-widget documentation surfaced in the admin help system
- Thumbnail registration from widget folder
- `required_on` manifest field replacing the PHP static array

---

## Open questions

1. **Button style classes (`btn-primary`, etc.) — widget-level or site-level?** Currently defined in `_custom.scss` with `@apply`. These are site-level styles that widgets consume, not widget-owned styles. They should probably stay in the site's SCSS and be exposed to the future style editor. The `_fields/buttons/` component references them but doesn't define them.

2. **Existing `css`, `js`, `code` columns** — drop them in a migration or leave them as nullable ignored columns for backward compatibility during the transition?

3. **Build automation** — should `widget:sync` automatically run `npm run build`, or just advise the user? On the deploy server, the deploy script already runs the build, so manual may be fine for local dev.

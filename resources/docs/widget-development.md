---
title: Widget Development Guide
description: Technical reference for building widgets — directories, pipeline, config fields, asset bundling, image handling, collections, and demo seeders.
version: "1.0"
updated: 2026-04-04
standalone: true
tags: [developer, cms, widgets, reference]
category: cms
---

# Widget Development Guide

Technical reference for building and maintaining page builder widgets. This document covers the full lifecycle of a widget: where files live, how data flows, what config fields are available, how assets are bundled, and how to create demo data for testing.

---

## Relevant Directories

| Location | Purpose |
|----------|---------|
| `resources/views/widgets/` | Blade templates (one per widget) |
| `resources/scss/widgets/` | Widget-specific SCSS partials (e.g. `_product-carousel.scss`) |
| `resources/js/public.js` | Public-facing JS — Swiper modules, Alpine, Chart.js |
| `resources/views/livewire/partials/inspector-fields/` | Config field UI partials (one per field type) |
| `app/Models/WidgetType.php` | Widget type definition model |
| `app/Models/PageWidget.php` | Widget instance model (per-page placement) |
| `app/Services/WidgetRenderer.php` | Rendering pipeline |
| `app/Services/WidgetDataResolver.php` | Collection/data resolution |
| `app/Services/PageBuilderDataSources.php` | Dynamic select option sources |
| `app/Services/AssetBuildService.php` | CSS/JS/SCSS bundling for public site |
| `app/Livewire/PageBuilderInspector.php` | Inspector panel — renders config fields |
| `database/seeders/WidgetTypeSeeder.php` | All built-in widget type definitions |
| `database/seeders/*DemoSeeder.php` | Demo data for widgets that use collections |
| `resources/views/components/page-widgets.blade.php` | Outer rendering loop (spacing, full-width) |

---

## Widget Composition

A widget type is defined by a record in the `widget_types` table. The key columns:

| Column | Purpose |
|--------|---------|
| `handle` | Unique machine name. Used in CSS class `.widget--{handle}` and for lookup. |
| `label` | Display name shown in the widget picker. |
| `description` | Short description shown in the widget picker. |
| `category` | JSON array of category slugs for filtering: `content`, `layout`, `media`, `blog`, `events`, `forms`, `portal`, `giving_and_sales`. |
| `allowed_page_types` | JSON array restricting to page types (`default`, `post`, `member`, `system`), or `null` for all. |
| `render_mode` | `server` (Blade) or `client` (JS). |
| `template` | For server mode: Blade string. Typically `@include('widgets.widget-name')`. |
| `code` | For client mode: raw JavaScript string. |
| `css` | Inline CSS stored in the database (compiled into the bundle). |
| `js` | Inline JS stored in the database (compiled into the bundle). |
| `assets` | JSON object with `scss`, `css`, `js` keys — file paths to external assets. |
| `collections` | JSON array of collection slot names (e.g. `['slides']`). Empty for non-collection widgets. |
| `config_schema` | JSON array of config field definitions (see below). |
| `default_open` | Whether the widget inspector auto-expands when placed. |
| `full_width` | Whether the widget renders edge-to-edge (no `site-container` wrapper) by default. |

Built-in widgets are registered in `WidgetTypeSeeder` using `WidgetType::updateOrCreate()`.

---

## Widget Pipeline

### How a widget goes from database to HTML

1. **Page load**: The page controller fetches all `PageWidget` records for the page, ordered by `sort_order`.

2. **Rendering** (`WidgetRenderer::render()`):
   - Loads the associated `WidgetType`.
   - **Config media**: For `image` and `video` config fields, resolves the uploaded file from the media library into a Spatie `Media` object (`$configMedia`).
   - **Collection data**: For each slot in `collections`, calls `WidgetDataResolver::resolve()` with the collection handle from config and any `query_config` parameters (`$collectionData`).
   - **Richtext processing**: Runs inline image replacement on richtext fields.
   - **Blade render**: Compiles the template string with `Blade::render()`, passing `$config`, `$configMedia`, `$collectionData`, and optionally `$children` (for column layouts).
   - Returns `['html' => ..., 'styles' => ..., 'scripts' => ...]`.

3. **Output** (`page-widgets.blade.php`):
   - Iterates rendered blocks.
   - Applies per-instance style config (padding, margin).
   - Wraps in `<div class="widget widget--{handle}">`.
   - If `full_width` is true (from widget type default or instance override), renders HTML directly. Otherwise wraps in `<div class="site-container">`.

### Template variables available to Blade

| Variable | Type | Contents |
|----------|------|----------|
| `$config` | `array` | Key-value pairs from the widget's config fields. |
| `$configMedia` | `array` | Spatie `Media` objects keyed by field name, for `image`/`video` fields. |
| `$collectionData` | `array` | Resolved collection data, keyed by collection slot name. |
| `$children` | `array` | (Column widgets only) Rendered HTML of child widgets. |

### Direct data access (non-collection widgets)

Some widgets bypass the collection system and call services directly from their template:

- **Product display**: `$pageContext->product($slug)` — loads a single product by slug.
- **Product carousel**: `WidgetDataResolver::resolveProducts($queryConfig)` — loads published products directly.
- **Event widgets**: `$pageContext->event($slug)` — loads a single event by slug.
- **Form widget**: `$pageContext->form($handle)` — loads a form by handle.

---

## Widget Parts

### Blade template

The primary rendering file. Located at `resources/views/widgets/{widget-name}.blade.php`.

Conventions:
- Extract config values into variables at the top in a `@php` block.
- Use `{{ }}` for escaped output, `{!! !!}` only for richtext fields that contain trusted HTML.
- Use Alpine.js `x-data` for client-side interactivity (Swiper init, toggles, etc.).
- Reference Swiper modules via `window.SwiperModules.*` and `window.Swiper`.

### SCSS

External SCSS partials go in `resources/scss/widgets/_widget-name.scss` and are referenced in the widget type's `assets.scss` array.

Conventions:
- Top-level class: `.widget-{widget-name}` (matches the wrapper class `.widget--{handle}`).
- Use BEM for child elements: `.product-slide__image`, `.product-slide__name`.
- Breakpoint variables `$bp-sm` and `$bp-md` are available (from `_variables.scss`).
- Do not use `@use` — the build server inlines `_variables.scss` at the top of the bundle.

### Inline CSS/JS

Small widgets can store CSS in the `css` column and JS in the `js` column of the widget type record directly. These are compiled into the public bundle alongside external assets.

### JavaScript

For client-side interactivity, prefer Alpine.js (`x-data`) inline in the Blade template. If you need heavier JS:
- For Swiper, Chart.js, or Alpine stores: use the globals already exposed in `resources/js/public.js`.
- For widget-specific JS that doesn't need a build step: use the `js` column on the widget type.
- For JS that requires npm packages: add the import to `resources/js/public.js` and expose it on `window`.

Currently exposed globals: `window.Swiper`, `window.SwiperModules` (Navigation, Pagination, Autoplay, EffectFade, EffectCoverflow, FreeMode), `window.calendarJs`, `window.Chart`, `window.Alpine`.

---

## Config Field Types

Config fields are defined in the `config_schema` JSON array on the widget type. Each field is an object with a `key`, `type`, `label`, and optional attributes.

### Available types

| Type | Renders as | Notes |
|------|-----------|-------|
| `text` | Text input | Single-line string. |
| `textarea` | Textarea | Multi-line string. |
| `richtext` | Quill editor | HTML content. Supports inline image upload. Output is trusted HTML — render with `{!! !!}`. |
| `number` | Number input | Numeric value. |
| `toggle` | Checkbox | Boolean. Stored as `true`/`false`. |
| `color` | Color picker + text | Hex color string. |
| `select` | Dropdown | Static options via `options`, or dynamic via `options_from`. |
| `image` | File upload | Stores to media library as `config_{key}` collection on the PageWidget. Accessible via `$configMedia['{key}']`. |
| `video` | File upload | MP4/WebM. Same media library pattern as image. |
| `url` | URL input | Validated URL string. |
| `buttons` | CTA button editor | Array of buttons, each with text, url, and style. Configure styles via `style_options`. |

### Field attributes

| Attribute | Type | Purpose |
|-----------|------|---------|
| `key` | string | **Required.** The config key. Accessed as `$config['key']` in the template. |
| `type` | string | **Required.** One of the types above. |
| `label` | string | **Required.** Display label in the inspector. |
| `default` | mixed | Default value when the widget is first placed. |
| `advanced` | bool | If true, moves the field into a collapsible "Advanced" section. |
| `options` | object | For `select` type: static `{value: label}` map. |
| `options_from` | string | For `select` type: dynamic data source name. |
| `depends_on` | string | For `select` with `options_from: 'collection_fields:*'`: names the config key that holds the collection handle. |
| `shown_when` | string | Config key — field is only visible when that key is truthy. |
| `hidden_when` | string | Config key — field is hidden when that key is truthy. |
| `group` | string | Groups fields visually in a grid row (fields with the same group value sit side-by-side). |
| `helper` | string | Hint text (used as placeholder in color fields). |
| `style_options` | object | For `buttons` type: customizes available button style choices. |

### Dynamic select sources (`options_from`)

| Source | Returns |
|--------|---------|
| `events` | Published events, keyed by slug. |
| `products` | Published products, keyed by slug. |
| `forms` | Active forms, keyed by handle. |
| `collections` | Active collections, keyed by handle. |
| `pages` | Published default-type pages, keyed by slug. |
| `collection_fields:{type}` | Fields from the collection selected in the `depends_on` field, filtered by field type. Example: `collection_fields:image` returns image-type fields. |

---

## Image Handling

### Config images (per-instance)

When a config field has `type: 'image'` or `type: 'video'`:
- The file is uploaded to the `PageWidget` model's media library under the collection name `config_{key}`.
- `WidgetRenderer` resolves it into a Spatie `Media` object and passes it as `$configMedia['{key}']`.
- In the template, use the `<x-picture>` component or call `$configMedia['key']->getUrl('webp')`.

### Model images (products, events, etc.)

Models that implement `HasMedia` register named media collections (e.g. `product_image`, `event_image`). Conversions follow the `ImageSizeProfile` pattern:
- `webp` — max-size WebP conversion.
- `responsive-{width}` — responsive breakpoints from site settings (default: 576, 768, 1024, 1280, 1536).

To get the URL in a data resolver or template: `$model->getFirstMediaUrl('collection_name', 'webp')`.

### Collection item images

Collection items store images in named media collections matching the field key. The `WidgetDataResolver` includes them in resolved data as `$item['_media']['{fieldKey}']`, which is a Spatie `Media` object.

---

## Asset Build Pipeline

Widget assets (SCSS, CSS, JS) are compiled by an external build server into public bundles.

### How it works

1. `AssetBuildService::collectSources()` gathers:
   - Site-level SCSS partials from `resources/scss/` in dependency order (`_variables`, `_base`, `_layout`, `_grid`, `_forms`, `_controls`, `_buttons`, `_icons`, `_media`, `_custom`).
   - Inline CSS/JS from `WidgetType.css` and `WidgetType.js` columns.
   - External files from `WidgetType.assets` paths (scss, css, js arrays).
2. Sources are POSTed to the build server with Bearer auth.
3. Compiled bundles are written to `public/build/widgets/` with content-hashed filenames.
4. `manifest.json` is updated. The public layout reads this to render `<link>` and `<script>` tags.

### Triggering a build

```bash
docker compose exec app php artisan build:public
docker compose exec app php artisan build:public --debug  # verbose output
```

### Adding assets to a widget

In the seeder, set the `assets` key:

```php
'assets' => ['scss' => ['resources/scss/widgets/_my-widget.scss']],
```

For inline CSS/JS (stored in the database), use the `css` and `js` columns instead.

After adding or changing widget assets, run `build:public` to recompile the public bundle.

---

## Collections for Widgets

Some widgets display data from content collections (carousel slides, logo gardens, board member lists, chart data). The collection system provides the data pipeline.

### How collections feed widgets

1. The widget type declares collection slots in its `collections` array (e.g. `['slides']`).
2. The widget's config includes a `collection_handle` select field pointing to a specific collection.
3. At render time, `WidgetDataResolver::resolve()` is called for each slot, which:
   - Looks up the `Collection` by handle.
   - Based on `source_type`, calls the appropriate resolver (`resolveCustom`, `resolveBlogPosts`, `resolveEvents`, `resolveProducts`).
   - Applies `query_config` parameters: `limit`, `order_by`, `direction`, `include_tags`, `exclude_tags`.
4. The resolved data array is passed to the template as `$collectionData['{slot}']`.

### Collection field types

Collections define their own field schema. Supported field types for collection items:

`text`, `textarea`, `rich_text`, `number`, `date`, `toggle`, `image`, `url`, `email`, `select`

### Source types

| Source type | Data source |
|------------|-------------|
| `custom` | JSONB items stored in `collection_items` table. |
| `blog_posts` | Published `Page` records with `type = 'post'`. |
| `events` | Published upcoming `Event` records. |
| `products` | Published, non-archived `Product` records. |

---

## Demo Seeders

Widgets that depend on collections need sample data for testing. Demo seeders create collections with representative items.

### Existing demo seeders

| Seeder | Creates | Used by |
|--------|---------|---------|
| `CarouselDemoSeeder` | `carousel-demo` collection with 4 slides (title, description, image fields) | Carousel widget |
| `ChartDemoSeeder` | `chart-demo` collection with 10 monthly data points (month, value fields) | Bar Chart widget |
| `LogoGardenDemoSeeder` | `logo-garden-demo` collection with logo items (name, url, image fields) | Logo Garden widget |
| `BoardMembersDemoSeeder` | `board-members-demo` collection with 6 members (name, photo, title, department, bio, social links) | Board Members widget |
| `ProductDemoSeeder` | 5 published products with 1-3 price tiers and sample images | Product Carousel and Product Display widgets |

### Writing a demo seeder

Pattern:

```php
class MyWidgetDemoSeeder extends Seeder
{
    public function run(): void
    {
        $collection = Collection::updateOrCreate(
            ['handle' => 'my-widget-demo'],
            [
                'name'        => 'My Widget Demo',
                'description' => 'Sample data for testing the my-widget widget.',
                'source_type' => 'custom',
                'fields'      => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'helpText' => '', 'options' => []],
                    ['key' => 'image', 'label' => 'Image', 'type' => 'image', 'required' => false, 'helpText' => '', 'options' => []],
                ],
                'is_public' => true,
                'is_active' => true,
            ]
        );

        $items = [
            ['title' => 'Item One'],
            ['title' => 'Item Two'],
        ];

        foreach ($items as $i => $data) {
            $item = CollectionItem::updateOrCreate(
                ['collection_id' => $collection->id, 'sort_order' => $i],
                ['data' => $data, 'is_published' => true]
            );

            // Attach image from sample-images directory
            if (isset($images[$i]) && file_exists($images[$i])) {
                $item->clearMediaCollection('image');
                $item->addMedia($images[$i])
                    ->preservingOriginal()
                    ->toMediaCollection('image');
            }
        }
    }
}
```

Register the seeder in `DashboardDebugGeneratorWidget::seedWidgetCollections()`:

```php
Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\MyWidgetDemoSeeder', '--force' => true]);
```

Sample images live in `resources/sample-images/{category}/` (e.g. `portraits/`, `products/`).

---

## Quick-Start Checklist for a New Widget

1. Add the widget type definition to `WidgetTypeSeeder.php` with handle, label, description, category, config_schema, and template reference.
2. Create the Blade template at `resources/views/widgets/{handle}.blade.php`.
3. If the widget needs custom styles, create `resources/scss/widgets/_{handle}.scss` and reference it in `assets.scss`.
4. If the widget uses a collection, add a slot to the `collections` array and include a `collection_handle` select in the config schema.
5. If the widget needs demo data, create a demo seeder and wire it into `seedWidgetCollections()`.
6. Run the seeder: `php artisan db:seed --class=WidgetTypeSeeder`.
7. Run the build: `php artisan build:public`.
8. Write tests covering the seeder, data resolution, and template rendering.
9. Update the widget count assertion in `WidgetPickerSession119Test`.

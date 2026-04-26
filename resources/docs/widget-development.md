---
title: Widget Development Guide
description: Technical reference for building widgets — directories, pipeline, config fields, inspector, asset bundling, image handling, collections, and demo seeders.
version: "1.2"
updated: 2026-04-12
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
| `app/Widgets/{PascalName}/` | Self-contained widget folder: definition class, Blade template, optional SCSS |
| `resources/views/widget-shared/` | Shared Blade fragments included by multiple widgets (buttons, icons, share icons) |
| `resources/js/public.js` | Public-facing JS — Swiper modules, Alpine, Chart.js |
| `app/Models/WidgetType.php` | Widget type definition model |
| `app/Models/PageWidget.php` | Widget instance model (per-page placement) |
| `app/Services/WidgetRenderer.php` | Rendering pipeline |
| `app/Services/PageBuilderDataSources.php` | Dynamic select option sources |
| `app/Services/AssetBuildService.php` | CSS/JS/SCSS bundling for public site |
| `app/Livewire/PageBuilder.php` | Page builder host: bootstrap data, add-widget modal, save-as-template modal |
| `resources/js/page-builder-vue/` | Vue inspector and editor app (Pinia store, REST persistence) |
| `resources/js/page-builder-vue/components/InspectorField.vue` | Field-type → Vue component map for `config_schema` rendering |
| `resources/js/page-builder-vue/components/fields/` | Vue field components (one per field type) |
| `resources/js/page-builder-vue/stores/editor.ts` | Pinia editor store: local state, debounced REST saves |
| `app/Providers/WidgetServiceProvider.php` | Registers the Blade `widgets::` namespace and every widget definition |
| `app/Widgets/Contracts/WidgetDefinition.php` | Abstract base class for every widget definition |
| `app/Services/WidgetRegistry.php` | Holds registered definitions; `sync()` writes them to `widget_types` |
| `database/seeders/WidgetTypeSeeder.php` | Cleans up retired handles; calls `WidgetRegistry::sync()` |
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
| `template` | For server mode: Blade string. Written by the definition's `template()` method — by default `@include('widgets::{Folder}.template')`. |
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
   - **Widget data**: If the widget's `WidgetDefinition::dataContract($config)` returns a contract, the renderer merges any user-supplied `query_config` knobs (`limit`, `order_by`, `direction`, `include_tags`, `exclude_tags`) into the contract's filters and resolves the contract through `ContractResolver`. The resulting DTO is exposed to the template as `$widgetData`.
   - **Richtext processing**: Runs inline image replacement on richtext fields.
   - **Blade render**: Compiles the template string with `Blade::render()`, passing `$config`, `$configMedia`, `$widgetData`, and optionally `$children` (for column layouts).
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
| `$widgetData` | `array\|null` | Contract-resolved DTO. Shape depends on the contract's source: `['items' => [...]]` for list-shaped sources (`SOURCE_SYSTEM_MODEL`, `SOURCE_WIDGET_CONTENT_TYPE`); a flat token map for `SOURCE_PAGE_CONTEXT`. Null when the widget declares no contract. |
| `$children` | `array` | (Column widgets only) Rendered HTML of child widgets. |

### Direct data access (non-contract widgets)

The Forms widget reads its model directly from `PageContext` rather than declaring a contract:

- **Form widget**: `$pageContext->form($handle)` — loads a form by handle.

---

## Widget Parts

### Blade template

The primary rendering file. Located at `app/Widgets/{PascalName}/template.blade.php`.

Conventions:
- Extract config values into variables at the top in a `@php` block.
- Use `{{ }}` for escaped output, `{!! !!}` only for richtext fields that contain trusted HTML.
- Use Alpine.js `x-data` for client-side interactivity (Swiper init, toggles, etc.).
- Reference Swiper modules via `window.SwiperModules.*` and `window.Swiper`.

### SCSS

Widget SCSS lives at `app/Widgets/{PascalName}/styles.scss` and is referenced in the widget definition's `assets()` method: `['scss' => ['app/Widgets/{PascalName}/styles.scss']]`.

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
| `color` | Color picker + text | Hex color string. Rendered by the shared `ColorPicker` primitive — see **Shared Appearance Primitives** below. |
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

## Inspector

The page builder inspector is a Vue island under `resources/js/page-builder-vue/`, mounted inside `resources/views/livewire/page-builder.blade.php`. It is **not** a Livewire component. The host Livewire class `App\Livewire\PageBuilder` is used only for: (1) generating the initial bootstrap data payload at page load, (2) the add-widget modal, and (3) the save-as-template modal. Everything else — selection, field rendering, mutation, save — is Vue + Pinia + REST.

### Tab structure

Each selected widget exposes a two-tab inspector:

| Tab | Component | Contents |
|---|---|---|
| **Content** | `InspectorField.vue` (per field) | Renders the widget type's `config_schema` field by field, dispatching to the field-type → component map. |
| **Appearance** | `WidgetAppearanceControls.vue` + `SpacingControl.vue` | Cross-cutting visual settings shared by all widgets: full-width toggle, background color, text color, padding, margin. |

The active tab is controlled by `InspectorTabs.vue`.

### Field-type → component map

`InspectorField.vue` holds the canonical mapping from `config_schema` field `type` to the Vue component that renders it. The current map:

| Field type | Vue component |
|---|---|
| `text`, `url` | `TextField.vue` |
| `textarea` | `TextareaField.vue` |
| `number` | `NumberField.vue` |
| `select` | `SelectField.vue` |
| `toggle` | `ToggleField.vue` |
| `checkboxes` | `CheckboxesField.vue` *(internal — not exposed in widget config schemas)* |
| `notice` | `NoticeField.vue` *(internal — used for setup notices, not stored on the widget)* |
| `richtext` | `RichTextField.vue` |
| `color` | `ColorPickerField.vue` |
| `image`, `video` | `ImageUploadField.vue` |
| `buttons` | `ButtonListField.vue` |

Adding a new field type requires: a new Vue component under `components/fields/`, a new entry in the `componentMap` in `InspectorField.vue`, and the corresponding entry in this guide's **Config Field Types** table.

### State and persistence

State lives in the Pinia store at `resources/js/page-builder-vue/stores/editor.ts`. Field components mutate the local store via helpers like `updateLocalConfig(widgetId, key, value)` and `updateLocalStyleConfig(widgetId, key, value)` — these update the store immediately so the inspector and preview reflect the change without a server round-trip. The inspector header exposes a widget-level "reset all settings to defaults" action (between the rename and delete buttons) that calls `clearAllOverrides(widgetId)`; the store zeroes the local config and the server's sparse-save persists `{}`, so every field falls back to its resolved default.

Each local mutation enqueues a debounced REST save: 350 ms after the last input event, the store calls `PUT /admin/api/page-builder/widgets/{id}` with the merged config / style_config / query_config payload. Pending changes for the same widget are coalesced into a single request. The server applies sparse-save — any config key whose value equals the resolved default is stripped before persisting. After a successful config-affecting save, the store also issues a preview refresh request to re-render the widget HTML server-side.

There is no `wire:model.live` binding anywhere in the inspector — Livewire is not in the data path for field edits.

### Defaults and the `resolved_defaults` wire contract

Each widget in the API/bootstrap payload carries a `resolved_defaults` map alongside `config`. This is the output of `WidgetConfigResolver::resolvedDefaults($pw)` — the composed defaults-plus-theme layer, without the instance overrides. The inspector reads a field's display value as `widget.config[key] ?? widget.resolved_defaults[key]` and uses the same map to decide whether a field is overridden (value present in `config` AND different from the resolved default). `field.default` from the schema is not consulted at display time. Because the renderer also draws defaults from the same resolver, the inspector display can never diverge from the rendered output. See `docs/widget-system.md` for the resolver's composition order and sparse-save rules.

### Bootstrap data

Initial state is generated by `App\Livewire\PageBuilder::getBootstrapData()` and rendered into the page builder blade as a JSON object. The Vue app reads it once on mount via `useEditorStore().loadTree(bootstrapData)`. The shape is mirrored in the `BootstrapData` TypeScript interface in `resources/js/page-builder-vue/types.ts`. After mount, all subsequent reads and writes go through the REST API under `/admin/api/page-builder/*`.

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

Collection items store images in named media collections matching the field key. The contract resolver (`WidgetContentTypeProjector`) includes them in resolved data as `$item['_media']['{fieldKey}']`, which is a Spatie `Media` object.

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

In the widget definition's `assets()` method:

```php
public function assets(): array
{
    return ['scss' => ['app/Widgets/MyWidget/styles.scss']];
}
```

For inline CSS/JS (stored in the database), use the `css` and `js` columns instead.

After adding or changing widget assets, run `build:public` to recompile the public bundle.

---

## Collections for Widgets

Some widgets display data from content collections (carousel slides, logo gardens, board member lists, chart data). The collection system provides the data pipeline.

### How collections feed widgets

1. The widget's config includes a `collection_handle` select field pointing to a specific collection.
2. The widget's `dataContract($config)` returns a `SOURCE_WIDGET_CONTENT_TYPE` contract carrying the collection handle plus a content-type schema (which fields the widget reads, image vs text).
3. At render time, `ContractResolver::resolveWidgetContentType` looks up the `Collection` by handle, fetches published `CollectionItem` rows with eager-loaded media, and projects each row through the contract's field whitelist.
4. User-supplied `query_config` knobs (`limit`, `order_by`, `direction`, `include_tags`, `exclude_tags`) are merged into the contract's filters before resolution. `order_by` is double-gated against the contract's `QuerySettings::orderByOptions` allowlist (UI dropdown + resolver re-validation).
5. The resulting DTO is passed to the template as `$widgetData['items']`. Each item carries exactly the contract's declared fields plus, when the content type declares image fields, a `_media` map of resolved media models.

### Collection field types

Collections define their own field schema. Supported field types for collection items:

`text`, `textarea`, `rich_text`, `number`, `date`, `toggle`, `image`, `url`, `email`, `select`

### Source types

`Collection.source_type` is dormant after Phase 4 — the column is set on existing rows but no production code reads it at render time. Widget data now flows through `ContractResolver` (system models via `SOURCE_SYSTEM_MODEL`, collection items via `SOURCE_WIDGET_CONTENT_TYPE`). A future cleanup session may drop the column.

---

## Demo Seeders

Widgets that need seeded fixtures for a functional preview ship their own `DemoSeeder` inside the widget folder. Sovereignty extends to demo data — no central demo-data service, no parallel manifest.

### Existing demo seeders

| Seeder class | Creates | Used by |
|--------------|---------|---------|
| `App\Widgets\Carousel\DemoSeeder` | `carousel-demo` collection with 4 slides (title, description, image) — images drawn from the still-photos sample library | Carousel widget |
| `App\Widgets\BarChart\DemoSeeder` | `chart-demo` collection with 10 monthly data points (label, value fields) | Bar Chart widget |
| `App\Widgets\LogoGarden\DemoSeeder` | `logo-garden-demo` collection with 9 logo items (name, logo image) | Logo Garden widget |
| `App\Widgets\BoardMembers\DemoSeeder` | `board-members-demo` collection with 6 members (name, photo, title, department, bio, social links) | Board Members widget |
| `App\Widgets\EventCalendar\DemoSeeder` | 3 published upcoming events (demo-event-1…3) | Event Calendar widget |
| `App\Widgets\DonationForm\DemoSeeder` | `demo-fund` Fund + `Spring Annual Appeal` Campaign | Donation Form widget |

Every seeder must be idempotent — running it on an already-seeded DB must not duplicate rows or error out.

Each declaring widget overrides `demoSeeder()` on its definition to return the FQCN:

```php
public function demoSeeder(): ?string
{
    return DemoSeeder::class;
}
```

`DashboardDebugGeneratorWidget::seedWidgetCollections()` iterates `WidgetRegistry::all()` and runs every non-null `demoSeeder()` — no hard-coded list.

### Config-only demos: `demoConfig()` and `demoAppearanceConfig()`

Widgets without a collection (e.g. `text_block`, `hero`, `video_embed`) supply their demo content through two optional methods on the definition:

| Method | Returns | Purpose |
|--------|---------|---------|
| `demoConfig(): array` | Config overrides | Merged on top of `defaults()` by the dev demo controller. Keys must match `schema()`. Example: `['content' => '<h2>Lorem ipsum</h2>…']`. |
| `demoAppearanceConfig(): array` | Appearance-config overrides | Padding, gradients, text color, etc. — same shape as the live `appearance_config` jsonb bag. Composed through `App\Services\AppearanceStyleComposer` and applied as inline style on the demo wrapper. |

Both default to `[]` on the base class and are only consumed by the dev demo route — production rendering is untouched.

### Writing a demo seeder

Place it at `app/Widgets/{PascalName}/DemoSeeder.php` with namespace `App\Widgets\{PascalName}`. Pattern:

```php
namespace App\Widgets\MyWidget;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\SampleImage;
use App\Services\SampleImageLibrary;
use Database\Seeders\SampleImageLibrarySeeder;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
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

        $this->call(SampleImageLibrarySeeder::class);
        $images = app(SampleImageLibrary::class)
            ->random(SampleImage::CATEGORY_STILL_PHOTOS, count($items));

        foreach ($items as $i => $data) {
            $item = CollectionItem::updateOrCreate(
                ['collection_id' => $collection->id, 'sort_order' => $i],
                ['data' => $data, 'is_published' => true]
            );

            // Attach an image pulled from the sample image library (see next section).
            $source = $images->get($i);
            if ($source) {
                $item->clearMediaCollection('image');
                $item->addMedia($source->getPath())
                    ->preservingOriginal()
                    ->toMediaCollection('image');
            }
        }
    }
}
```

Wire it into the widget definition by overriding `demoSeeder()`:

```php
public function demoSeeder(): ?string
{
    return DemoSeeder::class;
}
```

`DashboardDebugGeneratorWidget::seedWidgetCollections()` and the dev demo route will pick it up automatically via the registry.

### Sample image library

Demo imagery comes from a central pool managed as Spatie media attached to the `App\Models\SampleImage` host model. Four categories, one per folder under `resources/sample-images/`:

| Category constant | Folder | Used for |
|-------------------|--------|----------|
| `SampleImage::CATEGORY_PORTRAITS` | `portraits/` | Headshots, board/team members |
| `SampleImage::CATEGORY_STILL_PHOTOS` | `still-photos/` | Hero backgrounds, carousel slides, blog/event thumbnails |
| `SampleImage::CATEGORY_LOGOS` | `logos/` | Logo garden and similar brand rows |
| `SampleImage::CATEGORY_PRODUCT_PHOTOS` | `product-photos/` | Product carousel, product display |

**Swapping image sets:** drop new files into the appropriate folder and run `php artisan db:seed --class=Database\\Seeders\\SampleImageLibrarySeeder`. The seeder is idempotent and sync-style — new files are ingested, entries whose files have disappeared are deleted, unchanged files are left alone.

**Using the library from a demo seeder:** call `SampleImageLibrary::random($category, $count)` to get a collection of `Media` rows and attach each to your widget's CollectionItem.

```php
use App\Models\SampleImage;
use App\Services\SampleImageLibrary;
use Database\Seeders\SampleImageLibrarySeeder;

$this->call(SampleImageLibrarySeeder::class); // ensure the pool is populated
$images = app(SampleImageLibrary::class)->random(SampleImage::CATEGORY_STILL_PHOTOS, 4);

foreach ($items as $i => $data) {
    $item = CollectionItem::updateOrCreate(/* ... */);
    if ($source = $images->get($i)) {
        $item->addMedia($source->getPath())
            ->preservingOriginal()
            ->toMediaCollection('image');
    }
}
```

The same pool powers the dashboard random data generator via `App\Services\DemoDataService` — it maps image field keys (`logo`, `portrait`, `product`, etc.) to a category and returns a pool URL, falling back to `/images/sample-placeholder.png` when the pool is empty.

**Widgets backed by system collections** (ProductCarousel → `products`, EventsListing → `events`, BlogListing → `blog_posts`) do **not** ship demo seeders — their demo data flows through `DemoDataService` only. Do not add demo seeders for these.

### Declaring pool images for demo-mode thumbnails

Widgets whose `/dev/widgets/{handle}` capture renders an empty frame because they lack content can declare pool-image dependencies via `demoImages()` on the widget definition. `WidgetDemoController` reads the declaration at render time and injects URLs into config or into the shared appearance background slot.

```php
public function demoImages(): array
{
    return [
        [
            'category' => SampleImage::CATEGORY_STILL_PHOTOS,
            'count'    => 1,
            'target'   => 'appearance.background_image',
        ],
    ];
}
```

Targets:

- `appearance.background_image` — writes the first URL into `appearance_config.background.image_url`. The appearance composer renders it as a `background-image` when no `appearance_background_image` media is attached. Useful for Hero and any widget whose thumbnail benefits from a backdrop.
- `config.<key>` — writes the URL into `config.<key>`. If `count === 1` the value is a string; otherwise an array. The widget template chooses how to read it.

The pool returns `min(count, available)` — a widget that asks for 6 with only 2 in the folder receives 2. An empty pool yields no injection and the widget renders whatever it renders without images.

---

## Generating a thumbnail for your widget

Static PNG thumbnails are captured from the dev demo route (`/dev/widgets/{handle}`) by a host-side Playwright script at `scripts/generate-thumbnails.js`.

Host-level install (once per machine; **not** added to the project `package.json`):

```bash
npm install --global playwright
npx playwright install chromium
```

Capture one widget or all:

```bash
node scripts/generate-thumbnails.js --widget=my_widget
node scripts/generate-thumbnails.js --all
node scripts/generate-thumbnails.js --base-url=http://localhost
```

Each PNG is written to `app/Widgets/{PascalName}/thumbnails/static.png` at a fixed 800×500 viewport and committed to the repo alongside the widget's other files. If the result looks visibly wrong, fix the widget template — don't re-shoot until the underlying render is right.

---

## Declaring manifest metadata

Every widget definition inherits six optional manifest methods from `WidgetDefinition`. Metadata is code-only — it is not written to `widget_types` and is not part of `toRow()`. The widget browser UI reads it at runtime via `WidgetRegistry::manifests()`, and CI (`tests/Feature/WidgetManifestTest.php`) validates the contract.

| Method | Default | Notes |
|--------|---------|-------|
| `version(): string` | `'1.0.0'` | Must match `/^\d+\.\d+\.\d+$/`. Bump per-widget when a widget's contract changes. |
| `author(): string` | `'Nonprofit CRM'` | Leave as-is for first-party widgets. |
| `license(): string` | `'MIT'` | Allow-list: `MIT`, `Apache-2.0`, `GPL-3.0`, `BSD-3-Clause`, `proprietary`. |
| `screenshots(): array` | `[]` | Paths relative to the widget folder (e.g. `'screenshots/hero.png'`). Every path must exist on disk. |
| `keywords(): array` | `[]` | Lowercase slugs matching `/^[a-z0-9-]+$/` — used for browser search. |
| `presets(): array` | `[]` | Named config bundles (see shape below). |

The base class also exposes a `manifest(): array` aggregator that returns all six fields plus `handle`, `label`, `description`, and `category` in a single stable shape. Don't override it; override the individual getters.

### Preset shape

```php
public function presets(): array
{
    return [
        [
            'handle'            => 'dark-hero',        // slug, unique within the widget
            'label'             => 'Dark Hero',        // human-readable, non-empty
            'description'       => 'Short sentence.',  // string or null
            'config'            => [                   // appearance-group schema keys only
                'alignment'  => 'center',
                'min_height' => '32rem',
            ],
            'appearance_config' => [                   // appearance jsonb bag subset
                'padding' => ['top' => 80, 'bottom' => 80],
            ],
        ],
    ];
}
```

Every key in `preset.config` must appear in the widget's `schema()` **and** live under a field whose `group` is `appearance`. Presets are an appearance-layer feature — they may not touch content-group keys. CI enforces both rules and names the offending widget handle in the failure message.

### Presets in the inspector

The inspector panel exposes presets via a third "Presets" tab next to Content and Appearance. Each preset renders as a full-panel-width card (label + muted description, with a reserved empty thumbnail slot above). Clicking a card applies the preset with mixed semantics designed to leave content intact:

- `preset.config` is **overlaid** onto the widget's existing `config` — only the appearance-group keys the preset declares change; content-group keys (rich-text body, CTA buttons, media IDs) are preserved.
- `preset.appearance_config` **replaces** the widget's `appearance_config` wholesale — that bag is 100 % appearance, so nothing is preserved.

A synthetic "Blank" card is always prepended to the gallery. It is generated in the frontend from the appearance-group subset of the widget's `defaults()` plus an empty `appearance_config`, giving a one-click "reset appearance" option. It is not part of `presets()`.

Per-preset thumbnail images live at `app/Widgets/{PascalName}/thumbnails/preset-{handle}.png` and are captured via `scripts/generate-thumbnails.js` (the same host-side script that produces `static.png`). Cards render the PNG when it exists on disk and fall back to an empty placeholder otherwise. Detailed capture instructions live in `docs/widget-system.md`. DB draft presets do not get thumbnails.

### Authoring presets via the designer draft workflow

The preferred authoring path for a new preset is to iterate in the builder rather than hand-writing an array literal:

1. Open a page with an instance of the widget, tweak its appearance fields until it looks right, then click **Save current appearance as preset** in the inspector Presets tab. A `Draft N` card appears in the gallery.
2. Optionally rename the draft (click **Rename** on the card). Give it a stable `handle` (slug) and a short `description` at this point — that's what ends up in the code.
3. Apply the draft to other instances to verify it behaves correctly — the content of those instances is preserved; only appearance changes.
4. When satisfied, click **Export** on the draft card. A pretty-printed PHP array literal is written to your clipboard, trailing comma included so it drops straight into a `presets(): array` return list.
5. Paste the literal into the widget's `{PascalName}Definition::presets()` method.
6. Run `php artisan test --filter=WidgetManifestTest` to confirm the preset passes shape and appearance-group validation.
7. Delete the draft from the gallery — the code-authored version is now the source of truth.

Drafts live in the `widget_presets` table and are global per widget type (no per-user ownership). Any admin with `update_page` can see, rename, export, or delete any draft. The draft pool is a scratch surface; code remains the canonical preset source.

---

## Quick-Start Checklist for a New Widget

1. Create the widget folder at `app/Widgets/{PascalName}/`.
2. Create `{PascalName}Definition.php` extending `App\Widgets\Contracts\WidgetDefinition` with `handle()`, `label()`, `description()`, `schema()`, and `defaults()`. Override optional methods (`category`, `collections`, `assets`, `fullWidth`, `defaultOpen`, `allowedPageTypes`, `requiredConfig`, `css`, `js`) as needed. Override manifest metadata (`version`, `author`, `license`, `screenshots`, `keywords`, `presets`) only when the defaults don't fit — see "Declaring manifest metadata" below.
3. Create `template.blade.php` in the same folder. The base-class default `template()` method will find it via the `widgets::` namespace.
4. If the widget needs custom styles, create `styles.scss` in the same folder and return its path from `assets()`: `['scss' => ['app/Widgets/{PascalName}/styles.scss']]`.
5. Register the definition in `WidgetServiceProvider::boot()`: `$registry->register(new \App\Widgets\{PascalName}\{PascalName}Definition());`
6. If the widget uses a collection, add a slot to `collections()` and include a `collection_handle` select in `schema()`.
7. If the widget needs demo data, write `app/Widgets/{PascalName}/DemoSeeder.php` and override `demoSeeder()` on the definition to return `DemoSeeder::class`. The registry picks it up automatically.
8. Run the seeder: `php artisan db:seed --class=WidgetTypeSeeder`.
9. Run the build: `php artisan build:public`.
10. Write tests covering the data resolution and template rendering.
11. Update the widget count assertion in `WidgetPickerSession119Test` if widget total changes.

---

## Shared Appearance Primitives

A small set of reusable Vue components live under `resources/js/page-builder-vue/components/primitives/`. They are the building blocks every Appearance panel composes from. Use them directly when adding a new appearance control rather than rolling a one-off input — the visual language is shared across the inspector and the value shapes are stable.

This section documents what exists today. New primitives land in this section as they ship.

### `theme_palette` bootstrap data

The active page template's resolved color palette is exposed to the Vue editor through the bootstrap payload. It is consumed by the `ColorPicker` primitive (and any future primitive that wants theme colors).

**Source.** `App\Livewire\PageBuilder::getBootstrapData()` resolves the active template via `$page?->template ?? Template::query()->default()->first()` and calls `Template::resolvedPalette()` on it. Each entry runs through `Template::resolved()` so unset fields on the page's own template fall back to the default template's value.

**Shape.** An array of `{ key, label, value }` objects:

```json
[
  { "key": "primary_color",    "label": "Primary",            "value": "#4f46e5" },
  { "key": "header_bg_color",  "label": "Header Background",  "value": "#ffffff" },
  { "key": "footer_bg_color",  "label": "Footer Background",  "value": "#111827" },
  { "key": "nav_link_color",   "label": "Nav Link",           "value": null },
  { "key": "nav_hover_color",  "label": "Nav Hover",          "value": null },
  { "key": "nav_active_color", "label": "Nav Active",         "value": null }
]
```

`value` may be `null` if neither the page's template nor the default template defines that field — the picker renders those entries as disabled checkered chips.

**TS interface.** `ThemePaletteEntry` and the `theme_palette: ThemePaletteEntry[]` field on `BootstrapData` in `resources/js/page-builder-vue/types.ts`.

**Pinia store.** Available as `useEditorStore().themePalette` (a `ref<ThemePaletteEntry[]>`). The store populates it from the bootstrap data on `loadTree()`. Consuming primitives should read it from the store directly — they should not require it as a prop.

**Adding a new color to the palette.** Add the field to `Template::PALETTE_FIELDS` in `app/Models/Template.php` (key + display label). The helper iterates the constant, so the new color will appear in the bootstrap data and the picker without further wiring.

---

### `NinePointAlignment.vue`

A 3×3 grid of selectable points for choosing one of nine alignment positions. Compact (3rem square), keyboard-navigable, and accessible.

**File.** `resources/js/page-builder-vue/components/primitives/NinePointAlignment.vue`

**Props.**

| Prop | Type | Default | Notes |
|---|---|---|---|
| `modelValue` | `string` | `'center'` | One of the nine alignment names below. |
| `disabled` | `boolean` | `false` | Greyed out, pointer-events disabled, removed from the tab order. |
| `label` | `string` | `''` | Optional text label rendered above the grid. |

**Emits.** `update:modelValue` with the selected alignment string.

**Value shape.** A string from this set:

```
top-left      top-center      top-right
middle-left   center          middle-right
bottom-left   bottom-center   bottom-right
```

These map cleanly to CSS `background-position` values and to flex `align-items` / `justify-content` combinations.

**Keyboard.** Arrow keys move the selection one cell in the chosen direction (clamped at the edges). Enter / Space are no-op confirmations that keep focus consistent with other form controls.

**Accessibility.** The wrapper is a focusable element with `role="radiogroup"` and an `aria-label` that includes the current value (e.g. `"Alignment: top-right"`). Each cell has an SVG `<title>` for tooltip + AT fallback.

**Example.**

```vue
<script setup lang="ts">
import { ref } from 'vue'
import NinePointAlignment from '@/page-builder-vue/components/primitives/NinePointAlignment.vue'

const alignment = ref<string>('center')
</script>

<template>
  <NinePointAlignment v-model="alignment" label="Background position" />
</template>
```

---

### `ColorPicker.vue`

A dropdown color picker with a theme palette row (including a "no color" swatch), user swatches, and a persistent custom-color input (native HTML5 color wheel + hex text field). Replaces the old `ColorPickerField.vue`.

**File.** `resources/js/page-builder-vue/components/primitives/ColorPicker.vue`

**Props.**

| Prop | Type | Default | Notes |
|---|---|---|---|
| `modelValue` | `string` | `''` | Hex color string, or empty for "no color". |
| `label` | `string` | `''` | Optional text label rendered above the trigger. |
| `placeholder` | `string` | `'No color set'` | Shown in the trigger and custom hex input when empty. |

**Emits.** `update:modelValue` with the selected hex string, or `''` when the "no color" swatch is clicked.

**Slots.**

| Slot | Purpose |
|---|---|
| `icon` | Optional content rendered inside the trigger swatch. Used by the text-color variant in session 164 to overlay a "T" mark on the swatch without forking the primitive. |

**Storage.** Always a hex string (`#rrggbb` or `#rgb`), or empty. Token-based storage (palette references) is post-beta and out of scope.

**Theme palette flow.** The picker reads `useEditorStore().themePalette` directly — no prop wiring needed. Theme swatches at the top of the popover come from the active page template; if a palette entry has a `null` value (neither the page's template nor the default template defines it), the swatch renders as a disabled checkered chip. The "no color" swatch (white square with a diagonal red line) is the last entry in the theme row; clicking it emits `''`. See the **`theme_palette` bootstrap data** section above for the data flow.

**User swatches.** The picker also reads `useEditorStore().colorSwatches`, the existing per-user "saved colors" list backed by the `editor_color_swatches` site setting. Add via the dashed `+` swatch (saves the current value), remove by hovering a swatch and clicking the `×`. These persist via the existing `saveColorSwatches` store action.

**Custom color input.** A persistent native HTML5 color wheel + hex text input lives at the bottom of the popover under the "Add custom color" label. There is no toggle — both inputs are always visible. Either input writes its value through `update:modelValue` immediately; the wheel deals in `#rrggbb`, the hex input accepts whatever the user types and is the path for typing/pasting an exact value.

**Popover behaviour.** Click the trigger to open. Click outside, press Escape, or click the trigger again to close. The popover positions itself absolutely below the trigger.

**Example.**

```vue
<script setup lang="ts">
import { ref } from 'vue'
import ColorPicker from '@/page-builder-vue/components/primitives/ColorPicker.vue'

const color = ref<string>('')
</script>

<template>
  <ColorPicker
    v-model="color"
    label="Background color"
    placeholder="#ffffff"
  />
</template>
```

**Example with the icon slot (as session 164's text-color variant will use it):**

```vue
<ColorPicker v-model="textColor" label="Text color">
  <template #icon>
    <span class="text-color-mark">T</span>
  </template>
</ColorPicker>
```

---

### `GradientPicker.vue`

An inline-expanding gradient editor with eight built-in presets, a structured editor, and an optional second gradient layer. Composes the new `ColorPicker` primitive for the from/to stops.

**File.** `resources/js/page-builder-vue/components/primitives/GradientPicker.vue`

**Props.**

| Prop | Type | Default | Notes |
|---|---|---|---|
| `modelValue` | `GradientValue \| null` | `null` | The structured gradient value, or `null` for "no gradient". |
| `label` | `string` | `''` | Optional text label rendered above the trigger. |

**Emits.** `update:modelValue` with the new `GradientValue`, or `null` when cleared.

**Value shape.**

```ts
interface GradientLayer {
  type: 'linear' | 'radial'
  from: string         // hex
  to: string           // hex
  angle?: number       // degrees, 0–360, only meaningful for `linear`
  css_override?: string  // when non-empty, takes precedence over the structured fields
}

interface GradientValue {
  gradients: GradientLayer[]  // 1 or 2 layers; an empty array is treated as null
}
```

When the user clears the gradient, the picker emits `null` (not `{ gradients: [] }`) — easier for consumers to check with `if (value)`.

**Layer stacking.** A two-layer value renders with the second layer painting on top. The composition helpers reverse the array order before joining, so the input order matches the editor order ("Gradient 1" sits behind "Gradient 2") while the emitted CSS string lists Gradient 2 first.

**Presets.** Eight hard-coded presets live in a `const PRESETS` array at the top of the component. Edit that array to change the preset set — no schema or migration needed.

**Composition helpers.** Don't write the CSS string yourself in a consumer — use the matching helper for the rendering context:

| Context | Helper |
|---|---|
| Vue / TypeScript (editor preview, harnesses, client-side rendering) | `composeGradientCss(value)` from `resources/js/page-builder-vue/helpers/gradient.ts` |
| PHP / Blade (public widget renderer) | `App\Services\GradientComposer::compose($value)` |

Both helpers apply the same sanitization rules and produce the same CSS output for the same input. The PHP helper also has a `blank()` method that returns an explicit empty string for sites that want a named "no gradient" return.

**Sanitization rules.** Both helpers apply identical validation:

- **Hex colors** must match `^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$`. Anything else drops the layer.
- **Angles** must be numeric integers in `[0, 360]`. Anything else falls back to `180`.
- **Type** must be exactly `linear` or `radial`. Anything else drops the layer entirely.
- **CSS override path** uses a stricter allowlist: `^(?:linear|radial)-gradient\(\s*[#0-9a-fA-F,\s%.deg-]+\)$`. Anything containing `url()`, `expression()`, semicolons, quotes, or characters outside that allowlist is rejected — the override returns `''` and the layer is dropped.

The override is the only freeform input the picker takes from a user, so its validator is intentionally tight. Never bypass these helpers when rendering a stored gradient value — they are the security boundary.

**Example (Vue editor preview).**

```vue
<script setup lang="ts">
import { ref, computed } from 'vue'
import GradientPicker from '@/page-builder-vue/components/primitives/GradientPicker.vue'
import { composeGradientCss, type GradientValue } from '@/page-builder-vue/helpers/gradient'

const gradient = ref<GradientValue | null>(null)
const previewStyle = computed(() => ({
  backgroundImage: composeGradientCss(gradient.value),
}))
</script>

<template>
  <GradientPicker v-model="gradient" label="Background gradient" />
  <div class="preview" :style="previewStyle" />
</template>
```

**Example (Blade public renderer).**

```blade
@php
    $gradientCss = app(\App\Services\GradientComposer::class)->compose($block['appearance_config']['background']['gradient'] ?? null);
@endphp

<div
    class="widget widget--{{ $block['handle'] }}"
    @if ($gradientCss) style="background-image: {{ $gradientCss }}" @endif
>
    {!! $block['html'] !!}
</div>
```

---

## `AppearanceStyleComposer` — Server-Side Rendering

`App\Services\AppearanceStyleComposer` translates a widget's `appearance_config` jsonb into an inline style string and a full-width flag. It is called by the public renderer (`page-widgets.blade.php`) for every widget on the page.

**File.** `app/Services/AppearanceStyleComposer.php`

**Method.** `compose(PageWidget $pw): array` — returns `['inline_style' => string, 'is_full_width' => bool]`.

### Rendering pipeline

1. **Background color** — validates hex against `^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$`, emits `background-color:{hex}`.
2. **Background image layers** — composes gradient and image into a single `background-image` shorthand. Gradient paints **over** image (gradient first in the comma-separated list). Delegates gradient CSS generation to `GradientComposer::compose()`. Image URL comes from the `appearance_background_image` Spatie media collection on the `PageWidget` model.
3. **Image position and fit** — alignment string (e.g. `center`, `top-left`) is mapped to CSS `background-position` via a constant map. Fit (`cover` or `contain`) is emitted as `background-size`. Both emit only when an image is present.
4. **Text color** — same hex validation, emits `color:{hex}`.
5. **Padding and margin** — each of the four sides is cast to `int` and emitted as `{property}-{side}:{n}px`. Empty or non-numeric values are silently skipped — raw strings never reach the style attribute.
6. **Full-width resolution** — checks `layout.full_width` in `appearance_config`; if `null`, falls back to the widget type's `full_width` column. Column-child widgets (`layout_id IS NOT NULL`) are forced to `false` regardless.

### Security boundary

All values are validated or cast before reaching the inline style string. Hex colors are regex-checked. Numeric values are cast to `int`. Gradients go through `GradientComposer` which applies its own sanitization (see the **Sanitization rules** under `GradientPicker` above). No raw user input is ever emitted directly.

---

## Appearance Panels — Inspector Components

The Appearance tab in the inspector is composed of three panel components under `resources/js/page-builder-vue/components/appearance/`. Each panel receives the selected `Widget` as a prop and writes to the Pinia store via `store.updateLocalAppearanceConfig(widgetId, path, value)`.

### `BackgroundPanel.vue`

Controls: color picker, gradient swatch (toggles inline `GradientPicker` expansion), image upload/remove with thumbnail, nine-point alignment grid (disabled when no image), and a cover/contain fit selector (disabled when no image).

**Store interactions:**
- `updateLocalAppearanceConfig(id, 'background.color', hex)`
- `updateLocalAppearanceConfig(id, 'background.gradient', gradientValue)`
- `updateLocalAppearanceConfig(id, 'background.alignment', alignmentString)`
- `updateLocalAppearanceConfig(id, 'background.fit', 'cover' | 'contain')`
- `store.uploadAppearanceImage(id, file)` / `store.removeAppearanceImage(id)`

### `TextPanel.vue`

Controls: a single color picker with an "A" icon overlay (via the `#icon` slot on `ColorPicker`), plus a hint that inline rich text color overrides this value.

**Store interaction:** `updateLocalAppearanceConfig(id, 'text.color', hex)`

### `SectionLayoutPanel.vue`

Controls: full-width checkbox (disabled with tooltip for column-child widgets where `layout_id !== null`), padding group (All + Top/Right/Bottom/Left), margin group (same layout).

**Store interactions:**
- `updateLocalAppearanceConfig(id, 'layout.full_width', bool)`
- `updateLocalAppearanceConfig(id, 'layout.padding.{side}', value)`
- `updateLocalAppearanceConfig(id, 'layout.margin.{side}', value)`

The "All" shorthand displays the shared value when all four sides match, or `mixed` when they differ. Writing to it sets all four sides at once.

### Composition in `InspectorPanel.vue`

The three panels render in order on the Appearance tab: Background → Text → Section Layout. Below them, any per-widget `config_schema` fields with `group: 'appearance'` are rendered by the standard `InspectorFieldGroup` component.

---

## Session 162 — Per-Widget Config Key Changes

Session 162 renamed the `style_config` column to `appearance_config` and restructured the storage from flat keys to a nested shape. It also swept all built-in widgets to remove config keys that duplicated the universal Appearance layer. This table documents what changed per widget:

| Widget | Removed keys | Renamed keys | Notes |
|---|---|---|---|
| `hero` | `background_color`, `text_color`, `background_image`, `full_width` | `overlay_opacity` → `background_overlay_opacity` | `background_video`, `overlap_nav`, `nav_link_color`, `nav_hover_color` left alone (hero-specific) |
| `product_carousel` | `background_color`, `text_color`, `full_width` | — | |
| `bar_chart` | — | `bar_color` → `bar_fill_color` | Disambiguated from universal `text.color` |
| `carousel` | — | `slide_text_color` → `caption_text_color`, `slide_link_color` → `caption_link_color` | Disambiguated from universal `text.color` |
| `logo_garden` | — | `background_color` → `container_background_color` | CSS var: `--logo-bg` → `--logo-container-bg` |
| `board_members` | — | `background_color` → `grid_background_color` | CSS var: `--bm-bg` → `--bm-grid-bg`; `pane_color`, `border_color` left alone |
| `blog_listing` | — | — | Removed dead template reads of `background_color` / `text_color` (never in schema) |
| `events_listing` | — | — | Same dead-reference cleanup as `blog_listing` |

Removed keys are now provided by the universal Appearance layer (`appearance_config`). Renamed keys were disambiguated to avoid collision with the universal layer's similarly-named controls.

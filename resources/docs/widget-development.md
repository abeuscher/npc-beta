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
| `resources/views/widgets/` | Blade templates (one per widget) |
| `resources/scss/widgets/` | Widget-specific SCSS partials (e.g. `_product-carousel.scss`) |
| `resources/js/public.js` | Public-facing JS — Swiper modules, Alpine, Chart.js |
| `app/Models/WidgetType.php` | Widget type definition model |
| `app/Models/PageWidget.php` | Widget instance model (per-page placement) |
| `app/Services/WidgetRenderer.php` | Rendering pipeline |
| `app/Services/WidgetDataResolver.php` | Collection/data resolution |
| `app/Services/PageBuilderDataSources.php` | Dynamic select option sources |
| `app/Services/AssetBuildService.php` | CSS/JS/SCSS bundling for public site |
| `app/Livewire/PageBuilder.php` | Page builder host: bootstrap data, add-widget modal, save-as-template modal |
| `resources/js/page-builder-vue/` | Vue inspector and editor app (Pinia store, REST persistence) |
| `resources/js/page-builder-vue/components/InspectorField.vue` | Field-type → Vue component map for `config_schema` rendering |
| `resources/js/page-builder-vue/components/fields/` | Vue field components (one per field type) |
| `resources/js/page-builder-vue/stores/editor.ts` | Pinia editor store: local state, debounced REST saves |
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

State lives in the Pinia store at `resources/js/page-builder-vue/stores/editor.ts`. Field components mutate the local store via helpers like `updateLocalConfig(widgetId, key, value)` and `updateLocalStyleConfig(widgetId, key, value)` — these update the store immediately so the inspector and preview reflect the change without a server round-trip.

Each local mutation enqueues a debounced REST save: 350 ms after the last input event, the store calls `PUT /admin/api/page-builder/widgets/{id}` with the merged config / style_config / query_config payload. Pending changes for the same widget are coalesced into a single request. After a successful config-affecting save, the store also issues a preview refresh request to re-render the widget HTML server-side.

There is no `wire:model.live` binding anywhere in the inspector — Livewire is not in the data path for field edits.

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

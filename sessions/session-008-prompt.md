# Session 008 Prompt — Widget Type System + CMS Tags

## Context

Session 007 built a widget foundation using PHP classes (`app/Widgets/`) and a static `WidgetRegistry`. Post-session architectural review determined that widget types should live in the database rather than PHP files, support two rendering modes (server/client), and consume multiple collections with per-instance query config. This session replaces the PHP class system entirely and adds the CMS tag infrastructure that query filtering depends on.

The `page_widgets` table and `PageWidget` model from Session 007 are retained and extended. The PHP widget classes, abstract base, and static registry are retired.

---

## What This Session Builds

### 1. CMS Tag System

**`cms_tags` table:**
- `id` — UUID primary
- `name` — string, unique
- `slug` — string, unique (auto-generated from name via Spatie sluggable)
- timestamps

**`cms_taggables` pivot table** (polymorphic):
- `cms_tag_id` — UUID FK → cms_tags
- `taggable_type` — string
- `taggable_id` — UUID
- No timestamps, no primary key — composite index on all three columns

**`CmsTag` model**: `HasUuids`, `HasSlug`, `HasFactory`. `morphedByMany(CollectionItem::class, 'taggable', 'cms_taggables')`.

**`CollectionItem` model**: gains `morphToMany(CmsTag::class, 'taggable', 'cms_taggables')` relationship named `cmsTags`.

**`CmsTagResource`** in Filament: simple CRUD under Content group, sort 6. Columns: name, slug, item count. Form: name field only (slug auto-generated).

**`CollectionItemsRelationManager`**: add a `Select::make('cmsTags')` with `multiple()`, `relationship()`, and `createOptionForm()` so editors can tag items when editing collection items.

---

### 2. Widget Type Model + Migration

**`widget_types` table:**
- `id` — UUID primary
- `handle` — string, unique — machine identifier (alpha-dash)
- `label` — string — human name shown in page builder
- `render_mode` — enum: `server`, `client`
- `collections` — JSONB array of collection handles this widget declares it needs (e.g. `["blog_posts", "board_members"]`)
- `template` — text, nullable — Blade template (server mode only)
- `css` — text, nullable — inlined in `<style>` tag when widget is on page
- `js` — text, nullable — inlined in `<script>` tag when widget is on page
- `variable_name` — string, nullable — JS window variable name (client mode only)
- `code` — text, nullable — unrestricted code field (client mode only)
- timestamps

**`WidgetType` model**: `HasUuids`, `HasFactory`. Cast `collections` as array.

---

### 3. Modify `page_widgets` Table

New migration `modify_page_widgets_add_widget_type_id_and_query_config`:
- Drop `widget_type` string column
- Add `widget_type_id` UUID, FK → widget_types, cascade delete
- Add `query_config` JSONB, default `{}`

`PageWidget` model:
- Replace `widget_type` string with `widget_type_id`
- Add `query_config` cast as array
- Replace `typeInstance()` with `widgetType()` belongsTo relationship
- Remove dependency on `WidgetRegistry`

---

### 4. Retire PHP Widget Classes

Delete:
- `app/Widgets/Widget.php`
- `app/Widgets/WidgetRegistry.php`
- `app/Widgets/CollectionListWidget.php`
- `app/Widgets/BlogRollWidget.php`
- `app/Widgets/RichTextWidget.php`

Remove from `AppServiceProvider`:
- All `use App\Widgets\...` imports
- `WidgetRegistry::register([...])` call

Remove from `PageResource`:
- `PageWidgetsRelationManager` (no longer the mechanism for managing widgets on pages — see Session 009)
- `getRelationManagers()` method

---

### 5. WidgetDataResolver Refactor

New signature:
```php
public static function resolve(
    string $handle,
    array $queryConfig = []  // ['limit' => int, 'order_by' => string, 'direction' => string, 'include_tags' => [slugs], 'exclude_tags' => [slugs]]
): array
```

For `custom` source type:
- Apply `limit`, `orderBy`/`direction` (validate against collection field keys + `sort_order`)
- Apply `include_tags`: `whereHas('cmsTags', fn($q) => $q->whereIn('slug', $includeTags))`
- Apply `exclude_tags`: `whereDoesntHave('cmsTags', fn($q) => $q->whereIn('slug', $excludeTags))`
- Return `pluck('data')->all()` as before

For `blog_posts` source type:
- Apply `limit`, order defaults to `published_at desc`
- Tag filtering on Posts is not supported in this session (Posts do not yet have CmsTag relationship)
- Return mapped post arrays as before

---

### 6. Server Rendering Pipeline

`PageController` updated:

For each active `PageWidget`, load its `widgetType`. Then for each collection handle in `widgetType->collections`:
1. Call `WidgetDataResolver::resolve($handle, $pw->query_config[$handle] ?? [])`
2. Collect into `$collectionData[$handle]`

For server mode: call `Blade::render($widgetType->template, $collectionData)` to produce HTML output. Also collect CSS and JS from all active widgets on the page.

For client mode: produce `<script>window.{variable_name} = {json};</script>` for each collection, then append the widget's `code` field.

Pass to view:
```php
[
    'blocks' => $resolvedBlocks,  // [['html' => ..., 'css' => ..., 'js' => ...], ...]
    'inlineStyles' => $collectedCss,
    'inlineScripts' => $collectedJs,
]
```

Update `resources/views/pages/show.blade.php` and `resources/views/layouts/public.blade.php` to inject collected styles in `<head>` and scripts before `</body>`.

Update `resources/views/components/page-widgets.blade.php` to render `{!! $block['html'] !!}` for each block.

---

### 7. Widget Type Admin Resource

`app/Filament/Resources/WidgetTypeResource.php`

Navigation: Content group, sort 5, icon `heroicon-o-puzzle-piece`.

**Table columns:** label, handle, render_mode (badge: Server/Client), collections count, updated_at.

**Form:**
```
TextInput::make('label') — required
TextInput::make('handle') — alpha-dash, unique, auto-generated from label on create
Select::make('render_mode') — required, options: server/client, live()
Select::make('collections') — multiple, options from Collection::public()->pluck('name', 'handle')

// Server mode fields (visible when render_mode === 'server')
Textarea::make('template') — Blade template. Variables: collection handles named exactly as declared above.
Textarea::make('css')
Textarea::make('js')

// Client mode fields (visible when render_mode === 'client')
TextInput::make('variable_name') — JS identifier, alpha-dash
Textarea::make('code') — unrestricted. Warning: "This field executes arbitrary code in the browser. Use only code you trust."
Textarea::make('css')
```

Note: The `css` field is present in both modes but can be a shared field at the bottom of the form.

---

### 8. CollectionItem Admin Access Fix

Enable navigation on `CollectionItemResource`:
- Set `$shouldRegisterNavigation = true` (or remove the false line)
- Add a `SelectFilter` on the list page to filter by collection
- Navigation group: Content, sort 4.5 (or adjust Collections to sort 4, Items to sort 5, shift Widgets to 6, CMS Tags to 7)

Note: the `CollectionItemsRelationManager` tab may still not render due to the CSP issue. The standalone resource is the working fallback.

---

## Seeded Built-in Widget Types

After migrations, seed one built-in widget type via a seeder or migration:

**Text Block** (server mode):
- handle: `text_block`
- label: `Text Block`
- render_mode: `server`
- collections: `[]` (no collection dependency)
- template: `{!! $content ?? '' !!}` — config key `content` holds the WYSIWYG output (resolved by the page builder in Session 009)

This is the building block for prose content in the Session 009 page builder.

---

## Acceptance Criteria

- [ ] `CmsTag` CRUD works in admin; collection items can be tagged
- [ ] `WidgetType` CRUD works in admin; developer can create server and client mode widget types
- [ ] `page_widgets` table has `widget_type_id` and `query_config`; old `widget_type` string column removed
- [ ] PHP widget classes and static registry are fully deleted
- [ ] `WidgetDataResolver` applies limit, order, include-tags, exclude-tags correctly
- [ ] Server mode: `Blade::render()` produces HTML from template with collection variables
- [ ] Client mode: JSON injected into page, code field appended
- [ ] CSS/JS snippets from active widgets are collected and inlined in the page layout
- [ ] `CollectionItemResource` accessible via navigation with collection filter
- [ ] `php artisan test` passes

---

## What This Session Does Not Cover

- Page builder UI (Session 009)
- Removing `Page.content` field (Session 009)
- Tag filtering on Posts
- Widget preview in admin
- CSP root-cause fix

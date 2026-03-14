# Session 008 Log — Widget Type System + CMS Tags

**Date:** March 2026
**Status:** Complete. All 54 tests passing.

---

## What Was Built

### Migrations

- `2026_03_13_220001_create_cms_tags_table` — UUID PK, `name` (unique), `slug` (unique), timestamps
- `2026_03_13_220002_create_cms_taggables_table` — polymorphic pivot: `cms_tag_id`, `taggable_type`, `taggable_id`; composite index; no timestamps; no primary key
- `2026_03_13_220003_create_widget_types_table` — UUID PK, `handle` (unique), `label`, `render_mode` enum (server/client), `collections` JSONB, `template`, `css`, `js`, `variable_name`, `code`, timestamps
- `2026_03_13_220004_modify_page_widgets_add_widget_type_id_and_query_config` — drops `widget_type` string, adds `widget_type_id` UUID FK (cascade delete) and `query_config` JSONB default `{}`

### New Models

- `app/Models/CmsTag.php` — `HasUuids`, `HasSlug` (Spatie), `morphedByMany(CollectionItem, 'taggable', 'cms_taggables')`
- `app/Models/WidgetType.php` — `HasUuids`, `HasFactory`, `collections` cast as array, `hasMany(PageWidget)`

### Updated Models

- `app/Models/CollectionItem.php` — added `cmsTags()` `morphToMany(CmsTag, 'taggable', 'cms_taggables')`
- `app/Models/PageWidget.php` — replaced `widget_type` string and `typeInstance()` with `widget_type_id` and `widgetType()` belongsTo; added `query_config` cast as array; removed `WidgetRegistry` dependency

### PHP Widget Classes — Retired

Deleted:
- `app/Widgets/Widget.php`
- `app/Widgets/WidgetRegistry.php`
- `app/Widgets/CollectionListWidget.php`
- `app/Widgets/BlogRollWidget.php`
- `app/Widgets/RichTextWidget.php`
- `app/Widgets/` directory

### Updated Providers / Resources

- `app/Providers/AppServiceProvider.php` — removed all `App\Widgets` imports and `WidgetRegistry::register()` call
- `app/Filament/Resources/PageResource.php` — removed `PageWidgetsRelationManager` import and `getRelationManagers()`

### New Filament Resources

- `app/Filament/Resources/CmsTagResource.php` — Content group, sort 7; columns: name, slug, item count; form: name only (slug auto-generated)
- `app/Filament/Resources/CmsTagResource/Pages/` — ListCmsTags, CreateCmsTag, EditCmsTag
- `app/Filament/Resources/WidgetTypeResource.php` — Content group, sort 5, icon `heroicon-o-puzzle-piece`; form with conditional Server/Client sections; `handle` locked (read-only) on edit; default server template pre-populated; JS/CSS fields have starter comments
- `app/Filament/Resources/WidgetTypeResource/Pages/` — ListWidgetTypes, CreateWidgetType, EditWidgetType

### Updated Filament Resources

- `app/Filament/Resources/CollectionItemResource.php` — navigation enabled, Content group sort 5, `collection.name` column added, `SelectFilter` on collection added
- `app/Filament/Resources/CollectionResource/RelationManagers/CollectionItemsRelationManager.php` — added `Select::make('cmsTags')` with `multiple()`, `relationship()`, and `createOptionForm()`

### Services

- `app/Services/WidgetDataResolver.php` — refactored to `resolve(string $handle, array $queryConfig = [])`. New: `limit`, `order_by`, `direction` from config; `include_tags` / `exclude_tags` slug filtering via `whereHas` / `whereDoesntHave`; `orderBy` validated against allowlist (safe columns + collection field keys)

### Controllers

- `app/Http/Controllers/PageController.php` — new rendering pipeline: loads `widgetType` relation; resolves collection data per handle; server mode calls `Blade::render(template, collectionData)`; client mode injects `window.{variable_name} = {json}` and appends code field; collects `inlineStyles` and `inlineScripts` from all active widgets; passes `$blocks`, `$inlineStyles`, `$inlineScripts` to view; each block carries `handle` and `instance_id` for DOM targeting

### Views

- `resources/views/pages/show.blade.php` — passes `$blocks`, `$inlineStyles`, `$inlineScripts` to layout
- `resources/views/layouts/public.blade.php` — injects `$inlineStyles` in `<head>` via `<style>` tag; injects `$inlineScripts` before `</body>` via `<script>` tag
- `resources/views/components/page-widgets.blade.php` — iterates `$blocks`, wraps each in `<div class="widget widget--{handle}" id="widget-{instance_id}">` for type-level and instance-level CSS/JS targeting

### Seeder

- `database/seeders/WidgetTypeSeeder.php` — seeds built-in `text_block` widget type (server mode, empty collections, starter Blade template)

### Tests Updated

- `tests/Feature/PageWidgetTest.php` — rewritten for new model: creates `WidgetType` records, tests `widgetType()` relationship, removes all `WidgetRegistry` / PHP widget class references
- `tests/Feature/WidgetDataResolverTest.php` — fixed `limit:` named parameter to `['limit' => 3]` array syntax

---

## Decisions Made During Session

### Widget instance vs. widget type handles

`WidgetType.handle` is the type identifier (e.g., `text_block`). `PageWidget.id` is the instance identifier (UUID). The rendered DOM convention is:

```
class="widget widget--{handle}"   →  type-level CSS targeting
id="widget-{instance_id}"          →  instance-level targeting
```

This gives developers a clean, predictable hook for both shared and per-instance styles without additional schema work.

### `handle` locked after creation

The `handle` field on `WidgetTypeResource` is disabled on edit. Changing a handle after widget instances exist would silently break CSS targeting and any code referencing `widget--{handle}`.

### Default server template includes Alpine example

New widget types default to a starter Blade template with a working Alpine toggle. Rationale: developers unfamiliar with the stack immediately see that Alpine is available, and have something to delete rather than stare at a blank textarea.

---

## Known Issues / Deferred

- **Page builder UI** not yet built (Session 009). Widget types and instances exist in the database but there is no content editor interface for placing widgets on pages.
- **Collections taxonomy**: the current `Collection` model conflates schema definition (field list) with instance (container for items). Future sprint should split into `CollectionType` (schema) → `Collection` (instance) → `CollectionItem`. Separate nav items are the short-term workaround.
- **Code editor fields**: `template`, `js`, `css`, `code` fields are plain textareas. A Filament + CodeMirror/ACE community package would improve the developer experience. Deferred — estimated 1 session when prioritised.
- **CSP issue** from Session 007 unresolved — relation manager tabs in Filament still may not render. `CollectionItemResource` standalone nav is the working fallback.
- **Tag filtering on Posts** not implemented (Posts do not yet have `CmsTag` relationship).

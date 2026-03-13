# Session 007 Log — Widget System (Foundation)

**Date:** March 2026
**Status:** Foundation built; architecture revised post-session. Sessions 008–009 carry the work forward.

---

## What Was Built

### Data layer
- `database/migrations/2026_03_13_210000_create_page_widgets_table.php` — `page_widgets` with UUID PK, FK to pages, `widget_type` string, `label`, `config` JSONB, `sort_order`, `is_active`
- `app/Models/PageWidget.php` — `HasUuids`, `belongsTo(Page)`, `config` cast as array, `typeInstance()` helper
- `app/Models/Page.php` — added `pageWidgets()` hasMany

### PHP widget type system (to be replaced in Session 008)
- `app/Widgets/Widget.php` — abstract base class
- `app/Widgets/WidgetRegistry.php` — static registry
- `app/Widgets/CollectionListWidget.php`
- `app/Widgets/BlogRollWidget.php`
- `app/Widgets/RichTextWidget.php`
- `app/Services/WidgetDataResolver.php` — routes collection handles to CollectionItem or Post queries
- `app/Providers/AppServiceProvider.php` — registers three widget types on boot

### Admin
- `app/Filament/Resources/PageResource/RelationManagers/PageWidgetsRelationManager.php`
- `app/Filament/Resources/PageResource.php` — added `getRelationManagers()`

### Frontend
- `app/Http/Controllers/PageController.php` — loads active widgets, resolves data
- `resources/views/components/page-widgets.blade.php`
- `resources/views/widgets/collection-list.blade.php`
- `resources/views/widgets/blog-roll.blade.php`
- `resources/views/widgets/rich-text.blade.php`
- `resources/views/pages/show.blade.php` — added `<x-page-widgets>`

### Tests
- `tests/Feature/WidgetDataResolverTest.php` — 5 tests passing
- `tests/Feature/PageWidgetTest.php` — 4 tests passing
- Full suite: 54 tests passing

### Documentation
- `docs/decisions/013-widget-system-architecture.md`
- `docs/information-architecture.md` updated

---

## What Did Not Work

The `PageWidgetsRelationManager` tab does not render on the Page edit screen in Filament. The same issue affects `CollectionItemsRelationManager` (built in Session 006) — it also does not appear. Both relation managers are correctly wired in PHP.

**Root cause identified:** A Content Security Policy is blocking `eval()` in the browser, which prevents Alpine.js from initializing Livewire components dynamically. The CSP source was not found in nginx config, Laravel middleware, or Livewire config — likely a browser-level or Debugbar interaction. Not investigated further; architectural decision made to work around it.

**Consequence:** Collection items have no working admin UI (CollectionItemResource is hidden from nav, relying solely on the relation manager). Widget instances also have no admin UI.

---

## Architectural Decisions Made Post-Session

An extended design discussion after testing produced the following decisions, which define Sessions 008 and 009:

### Widget types move to the database

PHP classes in `app/Widgets/` will be retired. Widget types become DB records in a new `widget_types` table. Each record stores:
- `handle`, `label`
- `render_mode`: `server` or `client`
- **Server mode**: `template` (Blade), `css`, `js`
- **Client mode**: `variable_name` (JS window variable name), `code` (unrestricted code field), `css`
- `collections` JSONB: array of collection handles the widget consumes

### Two rendering modes

- **Server**: Collections injected as Blade variables by handle. Standard PHP rendering. JS/CSS inlined as enhancement.
- **Client**: Collection data written to `window.{variable_name}` as JSON. Single unrestricted code field handles everything. Escape hatch for complex frontend requirements.

### Query config per widget instance

`page_widgets` gains a `query_config` JSONB column. Per collection, the content editor can set: limit, order field + direction, include-tags (any), exclude-tags (none).

### CMS tag system — separate from CRM tags

A new `CmsTag` model and `cms_taggables` pivot, entirely separate from the existing CRM `Tag` model. Tags are a system field on `CollectionItem`, not a custom schema field. This separation allows future access-control scoping between CRM and CMS users.

### Page content becomes a block stack

`Page.content` will be removed. All page content — text sections and widget placements — is represented as an ordered `page_widgets` stack. A "text" widget type (server mode, WYSIWYG config field) is the replacement for prose content. Posts keep their single rich text body field for now.

### Two admin interfaces

1. **Widget Type Manager** (Session 008): Developer-facing CRUD for registering widget types.
2. **Page Builder** (Session 009): Content editor UI — ordered block list, inline WYSIWYG text blocks, drag-to-reorder, per-block query config.

---

## Session 008 / 009 references

See `sessions/session-008-prompt.md` and `sessions/session-009-prompt.md`.

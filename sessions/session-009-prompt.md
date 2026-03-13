# Session 009 Prompt — Page Builder

## Context

Session 008 replaced PHP widget classes with DB-stored `WidgetType` records and added the CMS tag system. Session 009 replaces the single `Page.content` rich text field with a block editor: an ordered stack of blocks where each block is either a text block (inline WYSIWYG) or a widget block (a `PageWidget` instance). This gives content editors full control over page layout without requiring developer involvement.

The `page_widgets` table (extended in Session 008) is the backing store for all blocks. A `text_block` widget type (seeded in Session 008) provides the prose content experience.

---

## What This Session Builds

### 1. Remove `Page.content`

Migration: drop the `content` column from the `pages` table.

Update `PageResource` form: remove the `RichEditor::make('content')` field.

Update `Page` model: remove `content` from `$fillable`.

No data migration needed — existing content is wiped (beta, no production data).

---

### 2. Page Builder — Custom Livewire Component

The Page edit screen gains a **Page Builder** section below the metadata fields (title, slug, publication, SEO). This is a custom Livewire component mounted inside the Filament Edit page.

**Component:** `app/Livewire/PageBuilder.php`
**View:** `resources/views/livewire/page-builder.blade.php`

The component:
- Accepts `$pageId` as a prop
- Loads `PageWidget` records for the page ordered by `sort_order`
- Manages block state in memory, persists on explicit save
- Communicates save completion back to the Filament page via a Livewire event

#### Block list rendering

Each block in the list shows:
- A drag handle (Alpine.js `x-sort` or equivalent)
- The block type label (`widgetType->label`)
- For text blocks: an inline Trix/Quill editor showing the block's content
- For widget blocks: the widget label, the collections it uses, and a summary of query config
- Edit button (opens a slide-over or modal)
- Delete button (confirmation required)
- Up/Down arrow buttons as fallback reordering if drag-and-drop is unreliable

#### Add block

An "Add Block" button opens a modal with:
- A list or select of available `WidgetType` records
- On selection: if text block, creates the PageWidget immediately and shows the inline editor. If widget block, opens the configure modal first.

#### Configure modal (widget blocks)

For non-text-block widgets, a modal form allows the content editor to configure:
- Per-collection query config (one section per handle in `widgetType->collections`):
  - Limit (numeric, optional)
  - Order by (select from collection fields + sort_order)
  - Direction (asc/desc)
  - Include tags (multi-select from CmsTag)
  - Exclude tags (multi-select from CmsTag)
- Admin label (optional, for identifying the block in the list)

This config is stored in `page_widgets.query_config` as:
```json
{
  "blog_posts": {
    "limit": 3,
    "order_by": "published_at",
    "direction": "desc",
    "include_tags": ["featured"],
    "exclude_tags": []
  }
}
```

#### Text block editing

Text blocks render an inline Trix editor (via Filament's RichEditor JS, or a plain Trix instance) directly in the block list. The content is stored in `page_widgets.query_config['content']` as HTML. On blur, the Livewire component updates the block state.

#### Drag-to-reorder

Use Alpine.js Sortable (via `@alpinejs/sort` plugin, or vanilla Sortable.js if available). On drop, update `sort_order` values for all blocks in the component state. Persist on save.

#### Mounting in Filament

In `app/Filament/Resources/PageResource/Pages/EditPage.php`, override `getFooterWidgetsColumns()` or use `->footerWidgets()` to mount `@livewire('page-builder', ['pageId' => $record->id])` below the form.

Alternatively, add a custom `Section` to the `PageResource` form that contains a `ViewField` rendering the Livewire component. This approach keeps it within the Filament form flow.

---

### 3. Public Rendering Update

`PageController` updated:
- Remove `$page->content` reference
- Load `PageWidget` blocks in sort order, resolve each via `widgetType` relationship
- For text blocks: `query_config['content']` is the HTML — no collection resolution needed
- For widget blocks: resolve collection data via `WidgetDataResolver`, render per mode
- Collect CSS/JS from all blocks (as established in Session 008)

`resources/views/pages/show.blade.php`:
- Remove `{!! $page->content !!}` block
- `<x-page-widgets :widgets="$widgets" />` remains (or is renamed to `$blocks`)

---

### 4. CollectionItem Admin Access (if not completed in Session 008)

If `CollectionItemResource` navigation was not enabled in Session 008, complete it here:
- Enable navigation under Content group
- Add SelectFilter for collection
- Form dynamically loads collection schema as in the relation manager

---

## Drag-and-Drop Implementation Note

`@alpinejs/sort` is the cleanest path given Alpine.js is already in the stack. Add the plugin CDN reference to the public layout. The Livewire component listens for sort events via `$wire.call('updateOrder', newOrder)`. If the CSP eval() issue persists and blocks Alpine plugin loading, fall back to up/down buttons with `wire:click` calls — this is ugly but functional.

---

## Acceptance Criteria

- [ ] `pages.content` column removed; no existing form fields reference it
- [ ] Page edit screen shows the Page Builder below the title/slug/SEO fields
- [ ] Admin can add a text block and type content inline
- [ ] Admin can add a widget block, select a widget type, and configure query params per collection
- [ ] Blocks reorder via drag-and-drop or up/down buttons; order persists on save
- [ ] Admin can delete a block
- [ ] Public page renders blocks in sort order; text blocks output HTML; widget blocks resolve and render
- [ ] CSS/JS from widget blocks are injected into page `<head>` / before `</body>`
- [ ] `php artisan test` passes

---

## What This Session Does Not Cover

- Widget preview in admin
- Post block editor (Posts keep single rich text body for now)
- CSP root-cause fix (if still unresolved)
- Widget type import/export
- Block-level visibility controls (show/hide per block)

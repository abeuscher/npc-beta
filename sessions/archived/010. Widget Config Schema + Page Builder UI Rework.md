# Session 010 Prompt — Widget Config Schema + Page Builder UI Rework

## Context

Session 009 built the Page Builder: an ordered block stack on each page, with text blocks
(inline Trix editor) and widget blocks (configure modal for query config). It works, but has
two problems that this session fixes:

1. **The text_block special case.** `PageController` and `PageBuilder` both check
   `handle === 'text_block'` to branch behaviour. This is a hardcoded exception that breaks
   the moment a second "inline content" widget type is needed. The fix is a proper
   `config_schema` system that makes text_block a regular widget.

2. **The block UI is wrong.** Session 009 used a Configure modal for widget block settings.
   The right pattern — agreed in session planning — is fully inline: every block is a
   collapsible card, fields appear in the expanded body, no modals for editing. The only
   modal is for adding a block, because add can be triggered from multiple positions.

Both are fixed in this session. The result is a page builder with no special cases and a
UX that doesn't make editors open and close modals to do their work.

---

## Part 1 — Widget Config Schema

### 1a. Migration

Add `config_schema` JSONB column to `widget_types`, default `[]`.

```sql
config_schema JSONB NOT NULL DEFAULT '[]'
```

The `config` column on `page_widgets` already exists and is already cast as array. No
migration needed there — it becomes the storage for per-instance singleton values.

### 1b. WidgetType model

Add `config_schema` to `$fillable` and cast as `array`.

### 1c. WidgetTypeResource form

Add a Repeater field for `config_schema` so developers can define singleton fields without
writing raw JSON. Each entry in the repeater has:

- `key` — machine name (alpha_dash, required, unique within the schema)
- `label` — human label shown in the page builder
- `type` — select from: `text`, `textarea`, `richtext`, `url`, `number`, `toggle`

Use the same repeater pattern as `CollectionResource` uses for its `fields` schema.

### 1d. PageController update

When rendering a page, pass `$pw->config` (the per-instance singleton values) to Blade
templates alongside collection data:

```php
$config = $pw->config ?? [];

// For server mode:
$html = Blade::render($widgetType->template, array_merge($collectionData, ['config' => $config]));
```

For text blocks specifically, after this session `config['content']` is the HTML — same
as before, but now driven by schema rather than a hardcode. The `handle === 'text_block'`
check in PageController is removed; text blocks are handled by the same server-mode path
as all other widgets, because `config_schema` tells the system what to do.

### 1e. Update text_block seeder / seed data

The `text_block` WidgetType record needs its `config_schema` set:

```json
[
  { "key": "content", "type": "richtext", "label": "Content" }
]
```

And its `template` updated to render from config:

```blade
{!! $config['content'] ?? '' !!}
```

If the seeder is idempotent (uses `updateOrCreate`), just update it and re-run. Otherwise
write a data migration.

---

## Part 2 — Page Builder UI Rework

The `PageBuilder` Livewire component and its view are significantly reworked. The configure
modal is removed. Everything moves inline.

### 2a. Block card structure

Each block renders as a card:

```
┌─────────────────────────────────────────────────────┐
│ [Block Type Label]  [Admin Label]          [⋯ menu] │  ← header, always visible
├─────────────────────────────────────────────────────┤
│  (collapsed by default — click header to expand)    │
│                                                     │
│  [singleton fields from config_schema]              │
│                                                     │
│  ▸ Query Settings  (collapsible subsection)         │
│    [per-collection limit / order / tags]            │
│                                                     │
└─────────────────────────────────────────────────────┘
```

- Clicking the header (or a chevron) toggles the body open/closed
- Body starts **collapsed** on page load for all blocks
- Collapse state is managed with Alpine `x-data` / `x-show` — no Livewire round-trip needed
- `wire:key="block-{{ $block['id'] }}"` on each card so Livewire tracks identity correctly
- A **drag handle** (grip icon, `heroicon-o-bars-2` or similar) sits on the left of each
  block header and is the only draggable target — clicking elsewhere on the header toggles
  collapse as normal

### 2b. Ellipsis menu (per block)

The `⋯` button opens a small dropdown (Alpine x-show) with:

- **Add Block Above** — opens Add Block modal, inserts at `index`
- **Add Block Below** — opens Add Block modal, inserts at `index + 1`
- **Copy Block** — duplicates the block (same widget type, same config, same query_config,
  sort_order = current index + 1, all subsequent blocks shift down)
- **Delete** — inline confirmation replaces the menu (same "Delete? Yes / Cancel" pattern
  as Session 009, but now in the dropdown area rather than the header bar)

Move Up / Move Down are removed — drag-to-reorder replaces them. Use `@alpinejs/sort`
(CDN, loaded before Alpine in the Filament panel head). On drop, call
`$wire.updateOrder(newOrderedIds)` where `newOrderedIds` is the array of block UUIDs in
their new sequence. The Livewire component persists `sort_order` for all blocks in one
pass.

If the CSP `eval()` issue (carried from Session 007) blocks `@alpinejs/sort` in the admin
panel, fall back to Up / Down options in the ellipsis menu and leave a clear code comment
marking the spot where the sort plugin should be wired in once CSP is resolved. Do not
silently drop the feature — make the fallback obvious.

### 2c. Singleton fields — inline in block body

For each field defined in `widgetType->config_schema`, render the appropriate input
directly in the block body:

| type | input |
|------|-------|
| `text` | `<input type="text">` with `wire:model.lazy` |
| `textarea` | `<textarea>` with `wire:model.lazy` |
| `richtext` | Trix editor with `wire:ignore` + `trix-change` Alpine listener (same pattern as Session 009 text block) |
| `url` | `<input type="url">` with `wire:model.lazy` |
| `number` | `<input type="number">` with `wire:model.lazy` |
| `toggle` | `<input type="checkbox">` with `wire:model` |

Wire model targets: `blocks.{index}.config.{key}`

On change, auto-save to DB immediately (same pattern as `updateTextContent` in Session 009,
generalised to any config key). Add a `updateConfig(string $blockId, string $key, mixed $value)`
method to the Livewire component.

### 2d. Query Settings — collapsible subsection

For widget blocks that declare collections, a "Query Settings" subsection appears at the
bottom of the expanded block body. It starts collapsed. Contains the same per-collection
fields as the Session 009 configure modal:

- Limit (number)
- Order By (select: sort_order, created_at, updated_at, published_at)
- Direction (asc/desc)
- Include Tags (checkboxes)
- Exclude Tags (checkboxes)

Changes auto-save via a `updateQueryConfig(string $blockId, string $collHandle, string $key, mixed $value)`
method. No save button.

For text blocks (and any widget with an empty `collections` array), this subsection does
not appear.

### 2e. Add Block modal

Triggered by:
- "Add Block Above" in ellipsis menu (position = index)
- "Add Block Below" in ellipsis menu (position = index + 1)
- "Add Block" button at bottom of list (position = end)

The Livewire component tracks `$insertPosition` (int|null — null means end).

Modal contents: a styled list or grid of available WidgetType records (icon, label,
short description if available). Clicking one creates the block at `$insertPosition`
and closes the modal. No dropdown select — present them as clickable cards so the
editor can see all options at a glance. (This also scales better than a select when
there are 8–10 widget types.)

### 2f. Copy Block

`copyBlock(int $index)` in the Livewire component:
- Loads the source `PageWidget` record
- Creates a new `PageWidget` with the same `widget_type_id`, `config`, `query_config`,
  `label`, `is_active`
- Inserts at `index + 1`, increments `sort_order` of all subsequent blocks
- Reloads block list

### 2g. Remove old configure modal

The `showConfigModal`, `configModalBlockIndex`, `configModalLabel`, `configModalQueryConfig`,
`openConfigModal()`, `saveConfigModal()`, `closeConfigModal()` properties and methods from
Session 009 are removed entirely.

---

## Part 3 — Remove All text_block Special Cases

After Parts 1 and 2 are complete, audit for and remove every `handle === 'text_block'`
check in:

- `app/Http/Controllers/PageController.php`
- `app/Livewire/PageBuilder.php`
- `resources/views/livewire/page-builder.blade.php`

The text_block widget should behave identically to any other server-mode widget with a
`richtext` field in its `config_schema`. If any special case remains after the audit,
that is a bug.

---

## Acceptance Criteria

- [ ] `widget_types.config_schema` column exists; WidgetType model casts it as array
- [ ] WidgetTypeResource form has a Repeater for defining singleton fields (key, label, type)
- [ ] `text_block` WidgetType has `config_schema` with one `richtext` field; template renders `$config['content']`
- [ ] PageController passes `$config` to Blade templates; no `handle === 'text_block'` check remains
- [ ] Block cards are collapsible; body starts collapsed on page load
- [ ] Singleton fields render inline in expanded block body; changes auto-save
- [ ] Query Settings subsection renders inline for blocks with collections; changes auto-save
- [ ] Drag handle visible on each block; drag-to-reorder works via `@alpinejs/sort` (or ellipsis Up/Down fallback if CSP blocks the plugin, with a comment marking the fallback)
- [ ] Ellipsis menu per block: Add Above, Add Below, Copy, Delete
- [ ] Add Block modal triggered from ellipsis menu and bottom button; inserts at correct position
- [ ] Copy Block duplicates a block with correct sort ordering
- [ ] No `handle === 'text_block'` checks anywhere in the codebase
- [ ] `php artisan test` passes

---

## What This Session Does Not Cover

- CSP root-cause fix (if the `eval()` issue blocks `@alpinejs/sort`, fallback is implemented but the underlying CSP issue is not resolved here)
- Block preview in admin
- Additional `config_schema` field types beyond the six defined above
- Block-level visibility controls
- Widget type import/export

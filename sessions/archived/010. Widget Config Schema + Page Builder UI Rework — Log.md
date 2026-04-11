# Session 010 Log — Widget Config Schema + Page Builder UI Rework

**Date:** March 2026
**Status:** Complete. All 54 tests passing.

---

## What Was Built

### Part 1 — Widget Config Schema

#### Migration

- `2026_03_14_000002_add_config_schema_to_widget_types` — adds `config_schema JSONB NOT NULL DEFAULT '[]'` to `widget_types`.

#### Updated Model

- `app/Models/WidgetType.php` — added `config_schema` to `$fillable`; cast as `array`.

#### Updated Filament Resource

- `app/Filament/Resources/WidgetTypeResource.php` — added a "Singleton Fields" `Repeater` section. Each repeater entry has `key` (alpha_dash), `label` (text), and `type` (select: text / textarea / richtext / url / number / toggle). Mirrors the pattern used in `CollectionResource` for its `fields` schema.

#### Updated PageController

- `app/Http/Controllers/PageController.php` — unified render path for all server-mode widgets. `$config = $pw->config ?? []` is merged into the Blade data alongside `$collectionData`. The `handle === 'text_block'` branch is fully removed. Client-mode path unchanged.

#### Updated Seeder

- `database/seeders/WidgetTypeSeeder.php` — changed `firstOrCreate` to `updateOrCreate`; sets `config_schema` to `[{key: content, type: richtext, label: Content}]`; template updated to `{!! $config['content'] ?? '' !!}`.

---

### Part 2 — Page Builder UI Rework

#### Rewritten Livewire Component

- `app/Livewire/PageBuilder.php` — major overhaul. Removed: configure modal props/methods (`showConfigModal`, `configModalBlockIndex`, `configModalLabel`, `configModalQueryConfig`, `openConfigModal`, `saveConfigModal`, `closeConfigModal`), `updateTextContent`. Added:
  - `$insertPosition` (int|null) — tracks insert position for Add Block modal
  - `$addModalLabel` (string) — required block label collected in Add Block modal
  - `loadBlocks()` — now includes `config` and `widget_type_config_schema` per block
  - `openAddModal(?int $position)` — accepts optional insert position; resets label
  - `createBlock(string $widgetTypeId)` — validates `addModalLabel` required; builds default config from `config_schema`; inserts at `$insertPosition` (shifting sort_order of subsequent blocks); uses provided label
  - `deleteBlock(string $blockId)` — takes block ID directly; Alpine handles confirm UI
  - `copyBlock(int $index)` — duplicates a block with `config`, `query_config`, `label`, `is_active`; inserts at index+1; shifts subsequent sort_orders
  - `updateOrder(array $orderedIds)` — persists new sort_order for all blocks after drag-to-reorder
  - `moveUp(int $index)` / `moveDown(int $index)` — kept as CSP fallback (see below)
  - `updateConfig(string $blockId, string $key, mixed $value)` — explicit save for richtext fields (Trix, which uses `wire:ignore`)
  - `updateQueryConfig(string $blockId, string $collHandle, string $key, mixed $value)` — explicit save for query config scalar values
  - `updated(string $name)` — Livewire lifecycle hook; auto-persists `wire:model`-bound changes to `blocks.N.label`, `blocks.N.config.*`, and `blocks.N.query_config.*`

#### Rewritten Blade View

- `resources/views/livewire/page-builder.blade.php` — complete rewrite. Key changes:
  - **Block cards** — collapsible via Alpine `x-data="{ open: false }"`. Body starts collapsed. Uses `<template x-if="open">` (not `x-show`) so Trix only mounts when the card is expanded.
  - **Block header** — drag handle (`x-sort:handle`) | label (primary, font-medium) | type badge (secondary, gray bg, small) | ellipsis menu. No configure button. No up/down buttons.
  - **Ellipsis menu** — Alpine `x-show` dropdown with: Add Block Above, Add Block Below, Copy Block, Delete (with Alpine inline confirm — no Livewire round-trip for confirm state). Move Up / Move Down are present but commented out as a labelled CSP fallback.
  - **Block body** — Label field (renamed from "Admin Label", helptext "for internal use"), then singleton config fields rendered per `widget_type_config_schema` (richtext uses Trix + `wire:ignore`; all others use `wire:model.lazy` or `wire:model`). Query Settings collapsible subsection at the bottom for blocks with collections (same fields as the old configure modal: limit, order_by, direction, include/exclude tags).
  - **Add Block modal** — required "Block Label" text input at top; widget types shown as a 2-column grid of clickable cards (handle + label); no dropdown select. Clicking a card calls `createBlock(id)` which validates the label server-side first.
  - **Drag-to-reorder** — `x-sort` on the block list container; `x-sort:item` on each block card. On drop, reads all `x-sort:item` IDs from the DOM in their new order and calls `$wire.updateOrder(ids)`.

---

### Part 3 — No More text_block Special Cases

Audited and removed all `handle === 'text_block'` checks:
- `PageController` — removed (unified server-mode path handles it)
- `PageBuilder` — removed (config defaults built from `config_schema`; `updateTextContent` replaced by `updateConfig`)
- `page-builder.blade.php` — removed (Trix rendered generically for any field with `type === 'richtext'`)

---

### Admin Panel

- `app/Providers/Filament/AdminPanelProvider.php` — two new `renderHook('panels::head.end', ...)` calls:
  1. Trix JS + CSS from jsDelivr CDN (`trix@2.0.8`) — required because Filament uses TipTap, not Trix, so Trix is not bundled.
  2. `@alpinejs/sort@3.14.3` CDN — enables drag-to-reorder.

---

### Filament Resource Updates

- `app/Filament/Resources/PageResource.php` — added a `Placeholder` field at the bottom of the title/slug section showing the public URL (`base_url` from SiteSettings + slug). Handles home page (slug `home` → `/`). Link opens in new tab. Hidden on create. Notes that slug changes require saving before the link updates.

---

### Test Updates

- `tests/Feature/PageWidgetTest.php` — `makeWidgetType()` helper updated: `config_schema` added with the richtext content field; template updated to `{!! $config['content'] ?? '' !!}`.

---

## Decisions Made During Session

### `x-if` over `x-show` for block bodies

Trix initializes when its element connects to the DOM. If the block body uses `x-show` (which hides via `display: none`), Trix initializes while hidden and the toolbar fails to render correctly when the card is expanded. Using `<template x-if="open">` defers DOM creation until the card is actually opened, giving Trix a visible container on first mount. The tradeoff is that re-opening a card re-creates the DOM (Trix re-initializes from the hidden input value), but `updateConfig` keeps the Livewire state current so the value is always correct.

### CSP fallback for drag-to-reorder

`@alpinejs/sort` drag handles work in testing. If the CSP `eval()` issue from Session 007 blocks the plugin, Move Up / Move Down are available as commented-out options in the ellipsis menu (the methods remain in `PageBuilder.php`). A TODO comment marks the location in both files. The fallback is fully functional; removing it is deferred until CSP is resolved.

### Trix not bundled in Filament

Filament 3 uses TipTap for its own `RichEditor` component. Trix is not included in Filament's compiled assets. Loading it from CDN in `AdminPanelProvider` is the correct approach for this panel; it should be revisited if a self-hosted asset pipeline is introduced.

### updateConfig vs wire:model + updated() hook

Richtext fields use `wire:ignore` (required to prevent Livewire from overwriting Trix's DOM), so `wire:model` cannot be used. These fields call `$wire.updateConfig(blockId, key, value)` directly from the Trix `trix-change` event. All other config and query_config fields use `wire:model.lazy` / `wire:model` and the Livewire `updated()` hook auto-persists the change to DB. No double-saves occur because richtext fields have no `wire:model` binding.

---

## Known Issues / Deferred

- **CSP `eval()` issue** — carried from Session 007. Drag-to-reorder may be blocked in some environments. Fallback is in place.
- **Trix CDN dependency** — Trix is loaded from jsDelivr. If a self-hosted asset pipeline is introduced, Trix should be bundled locally.
- **Block preview in admin** — not in scope for this session. Blocks are editable inline but not previewable within the admin panel.
- **`content` field migration for pre-existing text blocks** — blocks created in Session 009 stored their HTML in `query_config['content']`. The new system uses `config['content']`. Pre-session-010 blocks will render empty until their content is manually re-entered or a data migration is written. (Non-issue at current beta stage with no production data.)

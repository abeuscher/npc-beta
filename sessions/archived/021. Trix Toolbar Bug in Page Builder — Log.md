# Session 021 Log — Trix Toolbar Bug Investigation

**Date:** 2026-03-15
**Branch:** ft-session-021
**Committed:** No — work not committed. User opted to reset to main and start fresh in session 022.

---

## Goal

Diagnose and fix the missing Trix toolbar in page builder blocks. Richtext fields rendered
an 18×35px box with no toolbar and no formatting buttons, despite the editor area accepting
typing.

---

## Root Cause (Confirmed)

Two separate but related problems were found:

### 1. Trix never loaded on this page

Filament lazy-loads `rich-editor.js` (which bundles the entire Trix library and registers
the `<trix-editor>` custom element) only on pages where a native `RichEditor` form component
is rendered. `PageResource`'s edit page has no such field — the richtext editor lives inside
the custom `PageBuilder` Livewire component instead. So on this page, `customElements.get('trix-editor')`
returned `undefined`. The 18×35px box was an uninitialized, unknown HTML element, not a broken
editor.

**Verified:** Adding a scoped render hook in `AdminPanelProvider` to load `rich-editor.js` as
a `type="module"` script on `PageResource\Pages\EditPage` caused the editor to expand to full
size and initialize. Confirmed working — only the toolbar CSS was still missing (the icons
render as text labels without Filament's associated stylesheet).

### 2. Livewire's multiple-root detection

When attempting to remove the `x-if` wrapper on block bodies (to keep Trix in the DOM from
page load rather than stamping it on demand), Livewire threw:

```
MultipleRootElementsDetectedException: Multiple root elements detected for component: [page-builder]
```

This happens because Livewire's detection uses PHP's `DOMDocument::loadHTML()` (an HTML4
parser) to count body-level elements in the rendered HTML. `<trix-editor>` is an unknown
custom element in HTML4. Libxml can mishandle it and promote elements to the body level,
yielding a count > 1 from a component that visually has one root. The `<template x-if>`
wrapper accidentally protected against this by hiding `<trix-editor>` from libxml's parser —
`<template>` content is either discarded or treated as opaque by older libxml builds.

This was confirmed consistent with the Events page working fine: the Events EditPage renders
a large Filament page structure, so any libxml promotion lands inside that structure rather
than escaping to the body level.

---

## What Was Tried (All Reverted or Not Committed)

| Attempt | Result |
|---------|--------|
| Add explicit `<trix-toolbar>` inside wire:ignore | No effect — Trix ignored it without a matching `toolbar=` attribute on the editor |
| Add `toolbar="..."` attribute on `<trix-editor>` | No visible change |
| Switch `x-if` to `x-show` | Multiple root exception immediately |
| Remove `x-if` entirely (plain div) | Same multiple root exception |
| DOMContentLoaded guard script in AdminPanelProvider | Script ran too early; dynamic `<script>` tag created asynchronously, so Trix loaded after Alpine had already initialized |
| Scoped `type="module"` render hook in AdminPanelProvider | **Editor initialized and rendered at full size.** Toolbar icons missing (CSS not loaded). Partial fix. |

---

## Architecture Work Done (Not Committed)

### PageBuilder decomposed into parent + child components

To sidestep the multiple-root constraint while still allowing `x-show` on block bodies,
the block card was extracted into a dedicated child Livewire component:

- **New:** `app/Livewire/PageBuilderBlock.php`
- **New:** `resources/views/livewire/page-builder-block.blade.php`
- **Updated:** `app/Livewire/PageBuilder.php` — now manages the block list only; field
  persistence and config updates moved to the child; event listeners added via `#[On]`
  for child-dispatched actions (delete, copy, move, add)
- **Updated:** `resources/views/livewire/page-builder.blade.php` — block loop replaced
  with `@livewire('page-builder-block', [...], key(...))` calls

The child component's root element is the block card div, which wraps both the header and
the body (with `x-show`). This structure passes the multiple-root check because the card
div is the single root. `x-show` on the body means Trix is always in the DOM from page load.

This architecture is considered sound and should be re-implemented in session 022.

---

## Decision

Rather than continue patching Trix's CSS and fighting its coupling to Filament's asset
pipeline, the decision was made to replace Trix with **Quill**. Quill is framework-agnostic,
loads cleanly from CDN, supports font families (a UX requirement), and has no coupling to
Filament's lazy-loading system.

See `session-022-prompt.md`.

---

## Files Changed (Not Committed)

| File | Change |
|------|--------|
| `app/Livewire/PageBuilderBlock.php` | New — child block component |
| `resources/views/livewire/page-builder-block.blade.php` | New — child block template |
| `app/Livewire/PageBuilder.php` | Refactored — list management + event listeners |
| `resources/views/livewire/page-builder.blade.php` | Refactored — delegates block rendering to child |
| `app/Providers/Filament/AdminPanelProvider.php` | Scoped render hook for rich-editor.js; failed DOMContentLoaded guard (also present) |

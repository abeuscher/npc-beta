# Session 021 — Trix Toolbar Bug in Page Builder

## Context

The page builder (`resources/views/livewire/page-builder.blade.php`) renders blocks as
collapsible cards. Each card is collapsed by default and opens via an Alpine `open` toggle.
The block body uses `<template x-if="open">` so that content (including Trix editors) is
only mounted into the DOM when the card is open.

Blocks with a `richtext` field type render a `<trix-editor>` inside `wire:ignore`:

```html
<div wire:ignore>
    <input
        id="trix-{{ $block['id'] }}-{{ $field['key'] }}"
        type="hidden"
        value="{{ $block['config'][$field['key']] ?? '' }}"
    >
    <trix-editor
        input="trix-{{ $block['id'] }}-{{ $field['key'] }}"
        x-on:trix-change="$wire.updateConfig('{{ $block['id'] }}', '{{ $field['key'] }}', $event.target.value)"
        class="min-h-[120px] rounded border border-gray-300 bg-white p-2 text-sm"
    ></trix-editor>
</div>
```

## The Bug

When a block card is opened for the first time (Alpine injects the `x-if` content into
the DOM), the `<trix-editor>` renders but its toolbar is empty — no formatting buttons
appear. The editor area itself is present and accepts typing, but the toolbar is blank.

**This does not happen on the Events resource**, where Trix is used inside a Filament form
field that is visible on page load (not hidden inside an `x-if`). The toolbar works
correctly there.

## What Has Already Been Tried

- Adding an explicit `<trix-toolbar id="trix-toolbar-…">` element above the editor —
  no effect.
- Switching `<template x-if="open">` to `<div x-show="open">` — caused a Livewire
  "Multiple root elements detected" error on the EditPage component. Reverted.

Both approaches were reverted. The current file state has only the UUID quoting fix
(`x-sort:item="'{{ $block['id'] }}'"`) applied from this session.

## Hypothesis

`<trix-editor>` is a custom element. Its `connectedCallback` runs when the element is
inserted into the DOM. At that moment, the toolbar it looks for (either a sibling or
the default Trix toolbar appended to `<body>`) may not yet exist, or the Trix
`trix-toolbar-connect` event may fire before Alpine has fully initialized the surrounding
scope.

The Events page avoids this because the element is present in the initial DOM paint
and Trix's custom element registry fires at document-ready, not at dynamic insertion time.

## Goal for This Session

Diagnose and fix the toolbar rendering. The fix must:

1. Not use `x-show` on the block body (causes Livewire multiple-root error).
2. Not require removing `wire:ignore` (Livewire will overwrite Trix's DOM).
3. Work for all blocks that contain a `richtext` field, including blocks added after
   page load.
4. Not regress drag-to-reorder or any other page builder behaviour.

## Suggested Approach

Investigate whether dispatching a DOM event (e.g. re-triggering `trix-toolbar-connect`,
or calling `editor.toolbarController.didConnectToolbar()`) after Alpine inserts the
element resolves the issue. Alpine's `x-init` or an `x-on:x-if-rendered` equivalent
on the `wire:ignore` container may be the trigger point.

An `x-effect` or `@alpine:initialized` approach that watches `open` and calls a small
JS initializer on the Trix element is another candidate.

Keep the fix minimal and local to the richtext block rendering code.

## Files In Scope

| File | Why |
|------|-----|
| `resources/views/livewire/page-builder.blade.php` | Primary — where Trix is rendered |
| `public/js/` or `resources/js/` | Only if a small JS helper is needed |

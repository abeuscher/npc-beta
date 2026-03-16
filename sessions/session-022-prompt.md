# Session 022 — Replace Trix with Quill in the Page Builder

## Context

Session 021 investigated a bug where richtext fields in the page builder rendered as an
18×35px box with no toolbar. The root cause was confirmed: Filament lazy-loads `rich-editor.js`
(which contains the Trix library) only on pages with a native `RichEditor` form component.
The page builder page has none, so `<trix-editor>` was an unregistered custom element.

A secondary finding: removing the `x-if` wrapper on block bodies (which would keep Trix in
the DOM from page load) triggers Livewire's multiple-root detection, caused by PHP's
`DOMDocument` HTML4 parser misparsing the `<trix-editor>` custom element tag.

Rather than continue fighting Trix's coupling to Filament's asset pipeline, the decision was
made to replace Trix with **Quill**. Quill is framework-agnostic, loads cleanly from CDN,
supports font families (a UX requirement), and has no custom element registration issues.

Session 021's code was NOT committed. This session starts from the session 020 commit on main.

---

## Goals

1. Decompose `PageBuilder` into a parent + child Livewire component pair (work done in
   session 021, needs to be re-implemented cleanly here)
2. Replace the `<trix-editor>` rendering in richtext fields with a Quill editor instance
3. Load Quill from CDN, scoped to `PageResource\Pages\EditPage` via a render hook
4. Wire Quill's content changes back to the Livewire component
5. Remove all Trix-specific code and the broken DOMContentLoaded guard from `AdminPanelProvider`

---

## Component Architecture (Re-implement from Session 021)

### Parent: `PageBuilder`
- Manages the block list (`$blocks`, `$widgetTypes`)
- Handles add, delete, copy, reorder
- Listens for child-dispatched events via `#[On]`
- Blade template renders `@livewire('page-builder-block', [...], key(...))` for each block

### Child: `PageBuilderBlock`
- Owns one block's data (`$block` array loaded from DB by `$blockId`)
- Handles label and config field persistence via `updated()` and `updateConfig()`
- Dispatches events to parent for list-level operations (delete, copy, move, add above/below)
- Props: `$blockId`, `$isFirst` (Reactive), `$isLast` (Reactive)
- Blade template: block card with `x-show="open"` on the body (not `x-if`)

The child component's root element is the block card div containing both header and body.
This passes Livewire's single-root check regardless of the richtext field type used.
`x-show` keeps all fields in the DOM from page load so editors initialize at the right time.

---

## Quill Integration

### Loading

In `AdminPanelProvider`, add a render hook scoped to `PageResource\Pages\EditPage`:

```php
->renderHook(
    'panels::head.end',
    fn (): HtmlString => new HtmlString('
        <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
    '),
    scopes: \App\Filament\Resources\PageResource\Pages\EditPage::class,
)
```

Use Quill v2 (current stable). Snow theme matches the clean admin UI.

### Rendering in the block template

Replace the `<trix-editor>` block in the richtext field type branch with a Quill-mounted div:

```html
<div
    wire:ignore
    x-data="{
        init() {
            const quill = new Quill(this.$refs.editor, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ font: [] }, { size: [] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ color: [] }, { background: [] }],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['link'],
                        ['clean']
                    ]
                }
            });

            // Set initial content
            const initial = {{ json_encode($block['config'][$field['key']] ?? '') }};
            if (initial) quill.root.innerHTML = initial;

            // Persist changes back to Livewire
            quill.on('text-change', () => {
                $wire.updateConfig('{{ $field['key'] }}', quill.root.innerHTML);
            });
        }
    }"
    x-init="init()"
>
    <div x-ref="editor" class="min-h-[120px]"></div>
</div>
```

Key points:
- `wire:ignore` prevents Livewire from overwriting the editor DOM on re-renders
- `x-init` fires when Alpine initializes the element — since the body uses `x-show` (not
  `x-if`), this happens on page load when the DOM is fully settled
- `quill.root.innerHTML` is used for storage; it produces clean HTML compatible with
  standard rendering templates
- The `updateConfig` method signature on `PageBuilderBlock` is `updateConfig(string $key, mixed $value)` — no blockId needed since the child knows its own ID

### Font families

Quill's font module requires registering fonts before use. Add a small inline script after
the Quill CDN load to register the fonts desired (e.g. serif, monospace, or specific families).
Keep this minimal for now — the goal is having the option, not a full font library.

---

## AdminPanelProvider Cleanup

Remove the broken DOMContentLoaded guard added in session 021 (it was never committed, but
confirm it is not present in the starting state).

The scoped render hook for Quill replaces all Trix-related additions.

---

## Files Expected to Change

| File | Action |
|------|--------|
| `app/Livewire/PageBuilderBlock.php` | New |
| `resources/views/livewire/page-builder-block.blade.php` | New |
| `app/Livewire/PageBuilder.php` | Refactor — list management + #[On] listeners |
| `resources/views/livewire/page-builder.blade.php` | Refactor — delegate to child |
| `app/Providers/Filament/AdminPanelProvider.php` | Add Quill scoped render hook |

---

## Notes

- Session 022 was previously outlined as "Saved Import Field Maps." That work is unchanged
  and has been moved to **session 023**. See `session-022-outline.md` (now superseded by
  this prompt) and `session-023-outline.md`.
- The page builder's collapse/expand toggle can stay — `x-show` handles it fine now that
  the multiple-root issue is resolved by the child component architecture.
- Do not remove the drag-to-reorder (`@alpinejs/sort`) — it may work once the Livewire
  round-trip issues are resolved. The Up/Down ellipsis menu items remain as fallback.
- Run `php artisan test` before writing the session log.

# Inline Formatting Toolbar — Visual Guide & Behavior Specification

**Status:** Draft for implementation. Session 305, Phase 3.
**Replaces:** Interim formatting controls in the Inspector `RichTextField`.
**Scope:** The on-canvas floating toolbar that drives the active Quill instance during in-place text editing in the page builder.

---

## 1. Purpose

This document is the design contract for the inline formatting toolbar that appears when a user edits text directly inside a widget in the page builder. It defines (a) every visual element with precise specifications a designer or developer can build from, and (b) every behavior as a numbered, testable rule. There are no open questions or alternatives — every item is a decision.

This is a cornerstone tool. The content-editing experience is the page builder's central interaction; the toolbar is the surface the user touches every time they change anything written.

---

## 2. Hard constraints inherited

The spec must respect these and is not free to revisit them:

- The preview is **server-rendered HTML** dropped in via `v-html`; it is not a live document. One render path (`WidgetRenderer::render()`) feeds both preview and public site.
- The editor is **Quill v2**, loaded globally, not changing. The toolbar drives Quill through its public API (`quill.format`, `quill.getFormat`, `editor-change`); Quill's built-in toolbar module is disabled.
- The preview HTML can be replaced wholesale at any time (post-blur reconcile, save echo). A wholesale swap destroys the Quill instance inside it. The toolbar must therefore never hold a reference to a Quill instance.
- **Exactly one Quill instance is active at a time, and exactly one toolbar exists in the DOM.** The toolbar re-targets whichever instance is currently active.
- Clicking a toolbar control must not collapse the editor's selection or steal its focus. The one exception is the link popover's URL field.
- During editing, raw `{{token}}` text may be visible in place of resolved content. Resolution happens on the post-blur reconcile.
- The builder preview and the public page must render formatted output identically (single render path; the toolbar's output goes through the same sanitizer).
- The font family is controlled by the theme/Design System, not by this toolbar. There is no font-family control here.
- Plain-text fields exist and never show this toolbar.

---

## 3. Glossary

- **Picture** — the server-rendered HTML chunk that displays a widget's current state. Inert; not editable; can be replaced wholesale.
- **Field** — a `[data-config-key]` element inside a widget's Picture that has been annotated as inline-editable. A widget can have many fields.
- **Editor** — a Quill v2 instance mounted into a specific field. Ephemeral; created when the user clicks into a field, destroyed when the user leaves it or when the Picture is replaced.
- **Toolbar** — the single, persistent floating UI component this spec defines. Created once when the page builder mounts; never destroyed at runtime.
- **Active-editor handle** — the single shared piece of reactive state that the toolbar reads. Either `null` (no editor mounted) or a record carrying the live Quill instance, the host DOM node, the widget id, the config path, and a function to read the field's bounding rect.
- **Canvas** — the visible preview area of the page builder, bounded on the right by the Inspector panel.

---

## 4. Visual Guide

This section is the **design contract**. Every measurement, color, and glyph is specified. Mockup images, when rendered, must match these specs. Where this spec conflicts with a reference image previously shared, this spec wins.

### 4.1 Theme

The toolbar is **always dark**. It does not follow the surrounding admin's light/dark mode. Rationale: dark over light reads as "tool chrome, not content," visually separating the editor from the page being edited.

### 4.2 Geometry

| Property | Value |
|----------|-------|
| Bar height | 38px |
| Bar corner radius | 8px |
| Bar inner padding | 4px (all sides) |
| Bar drop shadow | `0 4px 12px rgba(0, 0, 0, 0.35)` |
| Button size | 32px × 32px |
| Button corner radius | 6px |
| Icon size | 18px (Lucide default, stroke-width 2) |
| Gap between buttons within a group | 0px |
| Group separator | 1px vertical line, 18px tall, vertically centered |
| Gap around a group separator | 6px on each side |
| Bar gap to anchored field | 8px |
| Canvas-edge clamp | 8px |

### 4.3 Color tokens

All values are the dark-theme palette; the toolbar does not have a light variant.

| Token role | Hex | Notes |
|------------|-----|-------|
| Bar background | `#1f2937` | gray-800 |
| Bar border | `#374151` | gray-700, 1px |
| Group separator | `#374151` | gray-700 |
| Icon (default) | `#d1d5db` | gray-300 |
| Button hover background | `#374151` | gray-700 |
| Button active pill background | `#4f46e5` | `--c-primary-600` |
| Icon on active pill | `#ffffff` | |
| Mixed-state pill background | `#4f46e5` at 35% opacity | overlaid on bar |
| Mixed-state icon | `#c7d2fe` | indigo-200 |
| Focus ring | `#818cf8` | 2px outline, 2px offset, `--c-primary-400` |
| Disabled icon | `#d1d5db` at 40% opacity | |
| Popover background | `#111827` | gray-900 |
| Popover border | `#374151` | 1px |
| Popover drop shadow | `0 8px 24px rgba(0, 0, 0, 0.45)` | |
| Popover input background | `#1f2937` | gray-800 |
| Popover input border | `#374151` | |
| Popover input focus border | `#818cf8` | |
| Popover text | `#e5e7eb` | gray-200 |
| Popover muted text | `#9ca3af` | gray-400 |

### 4.4 Icon set

**Lucide** for every button glyph (not Heroicons; Heroicons lacks editor glyphs). Lucide is delivered as static SVGs inlined into the DOM, same pattern as the existing `<x-svg-icon>` Heroicons component. The icon picker that opens for the "insert heroicon" button is the **existing `heroicon-picker.js`** component — that is reused unchanged.

### 4.5 Per-control state visuals

These apply to every applicable control. Not every state applies to every control — the per-control table in §4.7 indicates which.

| State | Visual |
|-------|--------|
| Default | Icon color `#d1d5db`, no background. |
| Hover | Background `#374151` (button rect), icon stays `#d1d5db`. Cursor `pointer`. |
| Focus-visible | 2px outline `#818cf8` at 2px offset, on top of any other state. Hover and focus can coexist. |
| Active ("on") | Background `#4f46e5`, icon `#ffffff`. The button reads as the format being on for the current selection. |
| Mixed | Background `#4f46e5` at 35% opacity, icon `#c7d2fe`. The button reads as "the selection contains both formatted and unformatted text for this format." |
| Disabled | Icon at 40% opacity, no hover response, `aria-disabled="true"`, click events ignored. |
| Pressed (active mousedown) | On a default/hover control: background `#111827` (gray-900, one step darker than hover). On an active control: background `#4338ca` (`--c-primary-700`, one step darker than active). No transform or icon shift. Released on mouseup. |

### 4.6 Groups, left to right (by popularity)

The bar's controls are organized into eight groups, separated by group separators, ordered by frequency of use. The leftmost group is the most-used; the rightmost is the least.

1. **Text style** (1 control — dropdown)
2. **Inline marks** (4 controls — B, I, U, S)
3. **Block structure** (3 controls — bulleted list, numbered list, blockquote)
4. **Link** (1 control)
5. **Alignment** (4 controls — left, center, right, justify)
6. **Color & highlight** (2 controls — text color, highlight)
7. **Insertions** (2 controls — image, heroicon)
8. **Clear formatting** (1 control)

Total: 18 buttons + 7 separators.

### 4.7 Control catalog

Every control in order. `aria-label` is the accessible name; `glyph` is the Lucide icon name (or the existing heroicon-picker SVG, noted explicitly); `shortcut` is the Quill-native keyboard shortcut (toolbar buttons do not override Quill's built-ins).

| # | Group | Control | Glyph | aria-label | Shortcut | States |
|---|-------|---------|-------|------------|----------|--------|
| 1 | Text style | Text-style dropdown | Label text + `chevron-down` | "Text style" | — | Default / hover / focus / open |
| 2 | Inline marks | Bold | `bold` | "Bold" | Cmd/Ctrl+B | Default / hover / focus / active / mixed |
| 3 | Inline marks | Italic | `italic` | "Italic" | Cmd/Ctrl+I | Default / hover / focus / active / mixed |
| 4 | Inline marks | Underline | `underline` | "Underline" | Cmd/Ctrl+U | Default / hover / focus / active / mixed |
| 5 | Inline marks | Strikethrough | `strikethrough` | "Strikethrough" | — | Default / hover / focus / active / mixed |
| 6 | Block structure | Bulleted list | `list` | "Bulleted list" | — | Default / hover / focus / active / mixed |
| 7 | Block structure | Numbered list | `list-ordered` | "Numbered list" | — | Default / hover / focus / active / mixed |
| 8 | Block structure | Blockquote | `quote` | "Blockquote" | — | Default / hover / focus / active / mixed |
| 9 | Link | Link | `link` | "Insert link" / "Edit link" | Cmd/Ctrl+K | Default / hover / focus / active (selection is inside a link) |
| 10 | Alignment | Align left | `align-left` | "Align left" | — | Default / hover / focus / active |
| 11 | Alignment | Align center | `align-center` | "Align center" | — | Default / hover / focus / active |
| 12 | Alignment | Align right | `align-right` | "Align right" | — | Default / hover / focus / active |
| 13 | Alignment | Justify | `align-justify` | "Justify" | — | Default / hover / focus / active |
| 14 | Color & highlight | Text color | `palette` + colored underbar | "Text color" | — | Default / hover / focus / open. Underbar reflects current text color or checker pattern if none. |
| 15 | Color & highlight | Highlight | `highlighter` + colored underbar | "Highlight" | — | Default / hover / focus / open. Underbar reflects current background color or checker pattern. |
| 16 | Insertions | Image | `image` | "Insert image" | — | Default / hover / focus / disabled (while upload in flight) |
| 17 | Insertions | Heroicon | existing `HEROICON_TOOLBAR_BUTTON_SVG` | "Insert icon" | — | Default / hover / focus / open |
| 18 | Clear formatting | Clear formatting | `remove-formatting` | "Clear formatting" | — | Default / hover / focus |

Overflow trigger (only visible when collapsed):

| # | Glyph | aria-label | Behavior |
|---|-------|------------|----------|
| O | `more-horizontal` | "More formatting options" | Default / hover / focus / open. Opens overflow menu. |

### 4.8 Text-style dropdown — closed and open

**Closed state**

- A control 120px wide × 32px tall, sitting in the leftmost group of the bar.
- Background matches the bar (no pill).
- Label text in `#e5e7eb`, 13px, Inter (or the bar's own UI font; not the theme's heading font).
- `chevron-down` Lucide glyph at the right edge of the control, 16px, `#9ca3af`.
- Label text rule:
  - Uniform Paragraph selection → "Paragraph"
  - Uniform Heading N selection → "Heading 1" … "Heading 6"
  - Selection spans multiple block formats → "Mixed"
- Hover: background `#374151` across the full control width.

**Open state (menu)**

- A popover anchored under the trigger, left-aligned to the trigger's left edge.
- Popover width: matches the trigger (120px minimum); grows if needed to fit longest row's intrinsic width up to a max of 280px.
- Popover background, border, shadow per §4.3.
- Seven rows, top to bottom: Paragraph, Heading 1, Heading 2, Heading 3, Heading 4, Heading 5, Heading 6.
- Each row renders its label in the **theme's actual font family and weight** for that level. Size is **proportional to the theme's ramp but capped at 22px**. Paragraph renders at body size (~13–14px). Headings scale up to 22px max; visual hierarchy is preserved without making H1 dominate the menu.
- Rows do **not** apply the theme's `text-transform` (e.g., uppercase) or `letter-spacing` values, even when the theme defines them. Both can compromise scannability at menu sizes; family + weight + clamped size capture each level's visual character without sacrificing readability.
- Row height: max(28px, label-intrinsic-height + 8px vertical padding). Horizontal padding: 12px.
- Row hover: background `#374151`.
- Currently-selected row: a 14px Lucide `check` glyph in `#818cf8` at the row's right edge, vertically centered.
- "Mixed" closed-state shows no `check` on any row.
- Keyboard: Up/Down arrows move highlight; Enter selects; Escape closes without selecting; first letter does not jump (rows aren't predictable enough).

### 4.9 Color / highlight popover

- Both buttons open a popover that contains the existing `ColorPicker.vue` component in **`panelOnly` mode** (no internal trigger; just the panel content).
- The popover frame is provided by the toolbar (dimensions, position, dark theme styling per §4.3); the inner panel is the unchanged `ColorPicker` panel content: Theme colors → My swatches → Add custom color.
- Popover width: 240px. Anchored under the button, left-aligned to the button's left edge; flips above if no room below.
- Selecting a swatch applies the corresponding format (`color` for text-color button, `background` for highlight button) to the current selection and closes the popover.
- Clicking the "No color" swatch removes the corresponding format from the selection.
- Selection is preserved across opening, applying, and closing (the popover does not take focus from the editor).
- Keyboard: Arrow keys navigate swatches in a grid (left/right/up/down); Enter/Space applies; Escape closes; Tab cycles through groups (Theme → Swatches → Custom hex). This is a small enhancement to the existing `ColorPicker.vue`; see §6 (Companion changes).
- Closes on: swatch click, Escape, click outside the popover and outside the editor.

### 4.10 Link popover

- Opens anchored under the Link button; flips above if no room.
- Popover width: 320px. Padding: 12px. Stacked form fields, top to bottom.

Field layout, top to bottom:

```
┌─────────────────────────────────────┐
│ URL                                 │
│ [https://example.com_______________]│
│                                     │
│ Or pick a page                      │
│ [▾ Search site pages…_______________]│
│                                     │
│ Link text                           │
│ [The selected text______________]   │
│                                     │
│ [✓] Open in new tab                 │
│                                     │
│ [Remove]            [Cancel] [Save] │
└─────────────────────────────────────┘
```

- **URL field:** `<input type="text">`, full width, 32px tall. Placeholder "https://example.com". Accepts any scheme allowed by the sanitizer (https, http, mailto, tel, relative paths).
- **Page picker:** typeahead combobox over `store.pages`. Closed = single-row 32px field with placeholder "Search site pages…" and a chevron at the right. Open = dropdown listing pages, filtered by typed query. Selecting a page populates the URL field with that page's current URL and shows the page name in the picker.
- **Mutual-exclusion rule:** the user can interact with either field. Selecting a page overwrites the URL field. Manually editing the URL field clears the page picker's selected page (the picker reverts to its empty/placeholder state). Both fields stay visible; only one is the source of truth at any moment.
- **Link text field:** pre-populated with the editor's currently-selected text. Editing it changes the link's visible text on Confirm.
- **"Open in new tab" checkbox:** unchecked by default. Per-link.
- **Buttons:**
  - **Remove** (left-aligned, secondary style) — only visible when editing an existing link (selection is inside a link or fully covers one). Removes the link format from the captured range, closes the popover.
  - **Cancel** (right-aligned, secondary style) — closes without applying any change.
  - **Save** (right-aligned, primary style) — applies the link to the captured range; if Link text differs from the original selected text, the link's visible text is updated; closes the popover.
- Confirm-on-Enter: pressing Enter in the URL field or Link-text field is equivalent to clicking Save.
- Escape: equivalent to Cancel.
- Click outside the popover: equivalent to Cancel.
- Focus on open: the URL field receives focus, with its content selected so the user can immediately type or paste over it.
- Focus trap: while open, Tab cycles URL → Page picker → Link text → Open in new tab → Remove (if visible) → Cancel → Save → URL.

### 4.11 Image button

- On click: opens a hidden file input scoped to `image/png, image/jpeg, image/gif, image/webp, image/svg+xml`.
- On file selection: uploads via the existing `store.inlineImageUploadUrl` endpoint (the same path the Inspector's `RichTextField` uses today).
- On upload success: inserts the returned URL into the editor via `quill.insertEmbed(range.index, 'image', url)` at the captured insertion point.
- On upload failure: a small inline error toast appears below the bar for 4 seconds (`#dc2626` background, white text, 13px), then dismisses. The selection and bar state are unchanged.
- The button is disabled with a small inline spinner while an upload is in flight.

### 4.12 Heroicon button

- On click: opens the **existing `heroicon-picker.js`** popover anchored to the button. No new picker UI is introduced.
- On selection: inserts the chosen icon via `quill.insertEmbed(range.index, 'heroicon', icon, Quill.sources.USER)` — the same call the existing Inspector `RichTextField` makes.
- Selection is preserved; popover closes on selection or Escape.

### 4.13 Clear formatting button

- On click: removes all inline marks (bold, italic, underline, strikethrough, color, background, link) from the current selection. Block format (heading level, list, blockquote, alignment) is **not** reset by this button.
- If the selection is collapsed, this button is disabled.

### 4.14 Overflow menu

- A `more-horizontal` button appears at the right edge of the bar when one or more groups have been collapsed (see §J for the collapse ladder).
- On click: opens a vertical menu popover anchored under the overflow button (flips above if no room).
- Menu width: 240px. Padding: 4px vertical, 0 horizontal. Each item: 36px tall, 12px horizontal padding.
- Items in the overflow menu are grouped using the same group order as the bar, with section headers (10px, `#9ca3af`, all caps) for each group ("Alignment", "Color & highlight", "Insertions", "Clear formatting").
- Each item shows: Lucide glyph (left, 18px), label text (12px, `#e5e7eb`), and any active/mixed indicator at the right edge.
- Closes on: item click, Escape, click outside.

### 4.15 Reference mockups (when rendered)

Mockup images, if produced, must depict each of the following states. Every spec value above is the source of truth; the mockups are renderings of this spec.

1. **Bar at rest, full width** — all 18 buttons visible, no separator hover, no active controls (selection is plain unformatted text in a Paragraph block).
2. **Bar with active toggles** — Bold + Italic + Heading 2 + Align left active; one mixed state (e.g. Underline mixed).
3. **Bar with text-style menu open** — showing Paragraph through Heading 6, each row in its theme style capped at 22px; H2 row highlighted as "currently selected."
4. **Bar with link popover open** — empty state (creating a new link).
5. **Bar with link popover open** — edit state (selection is inside an existing link; fields pre-populated; Remove button visible).
6. **Bar with color popover open** — Theme colors + My swatches + Custom color groups visible.
7. **Bar with overflow menu open** — at Step 3 collapse (Alignment, Color & highlight, Insertions, Clear formatting in overflow); overflow menu open showing all four sections.
8. **Bar at narrow widths** — Step 1 (clear hidden), Step 2 (clear + insertions hidden), Step 3 (clear + insertions + color/highlight hidden), Step 4 (alignment also hidden), floor (wrapped to two rows).
9. **Bar pinned to viewport top** while the user edits the bottom half of a tall field (tall-blog-body scenario).
10. **Suppressed corner affordances** — a widget with its drag-grip and Edit button hidden because a field inside it is being edited.

---

## 5. Behavior Specification

Each rule is independently testable. Rules are numbered; cross-references use the rule number.

### A. Lifecycle and the active-editor handle

A1. Exactly one toolbar component instance exists at runtime, mounted at the page-builder Vue app root.

A2. The toolbar component is created once when the page builder mounts and is never created or destroyed at runtime.

A3. The toolbar's visibility and binding state are a pure function of a single shared reactive value: the **active-editor handle**.

A4. The active-editor handle is either `null` or a record with these fields:
  - `quill`: the live Quill v2 instance.
  - `hostEl`: the DOM element Quill is mounted into.
  - `widgetId`: the widget id whose config is being edited.
  - `configPath`: the dot-addressed config path being edited.
  - `getRect()`: a function returning the host element's current bounding rect.

A5. The handle is set (non-null) at the moment the inline-editing layer mounts a Quill instance into a rich-text field, and only at that moment.

A6. The handle is cleared (set to `null`) the moment that Quill instance is torn down, for any reason whatsoever: user blur, re-target click, widget re-render, widget deselection, widget deletion, page-builder unmount.

A7. The toolbar holds no direct reference to the Quill instance; every operation reads `handle.value.quill` at call time.

A8. When the handle is null, the toolbar renders nothing visible, holds no positional listeners, and references no editor DOM node.

A9. When the handle transitions from null to non-null, the toolbar (in order): binds a `Quill.editor-change` listener to `handle.quill`, binds a `ResizeObserver` to `handle.hostEl`, binds a throttled scroll listener to the canvas, binds a window resize listener, computes its initial position, and begins fading in (§B3).

A10. When the handle transitions from non-null to null, the toolbar (in order): unbinds all listeners bound in A9, hides itself (§B8), and discards all references to the previous handle's contents.

A11. When the handle transitions from one non-null value to another (re-target), the toolbar performs the A10 steps for the previous handle, then the A9 steps for the new handle, in that order.

A12. The inline-editing layer is the sole publisher of the handle. The toolbar is the sole consumer.

A13. **Primary defense against wholesale HTML replacement:** while the handle is non-null, preview-HTML replacement for the widget containing the active field is suppressed via the existing `store.inlineActiveWidgetId` mechanism. No save echo, post-save reconcile, or unrelated refresh may swap that widget's preview HTML while editing is in progress. The reconciling refresh that resolves tokens and re-renders the block fires only after the editor has been intentionally torn down (blur, re-target, deselection).

A14. **Defense-in-depth fallback for wholesale HTML replacement:** the inline-editing layer observes the active field's host element for removal from the document — via a `MutationObserver` on the host's parent subtree (or equivalent removal-detection). If the host element is removed from the document by any code path that bypasses A13, the handle is cleared synchronously within the same microtask. Combined with A6, this guarantees the toolbar can never be left bound to a torn-down editor; the corner-ghost bug is structurally impossible. A13 is the primary defense; A14 closes the gap A13 cannot guarantee unilaterally.

### B. Appear, disappear, re-target

B1. The toolbar appears when the user clicks a `[data-config-key]` field of `data-config-type="richtext"` inside a selected, inline-eligible widget and the inline-editing layer mounts a Quill instance there.

B2. The toolbar does not appear on widget selection alone. There must be a live Quill instance.

B3. On appear, the toolbar fades in over 80ms (ease-out). When `prefers-reduced-motion: reduce` matches, the bar appears instantly.

B4. The active editor tears down — clearing the handle and hiding the toolbar — on a `pointerdown` event whose target is outside the editor's host element, outside the toolbar, and outside any toolbar-owned popover. This is the single trigger; "blur followed by next interaction outside" is not the rule.

B5. The toolbar disappears when the active editor is destroyed by a widget re-render that swaps the preview HTML wholesale, via the handle being cleared.

B6. The toolbar disappears when the user clicks an unrelated widget; selection moves, the previous editor blurs, the handle clears.

B7. The toolbar disappears when the widget being edited is deleted from the page; the handle clears.

B8. On disappear, the toolbar fades out over 80ms (ease-in). Under `prefers-reduced-motion: reduce`, the bar disappears instantly.

B9. On **cross-widget re-target** (handle moves between editors in different widgets), the toolbar fades out (80ms), repositions to the new field, then fades in (80ms). No sliding/translation animation between positions. The duration accounts for the widget-level preview re-render that occurs between the two editors and would otherwise be visible under a faster transition.

B10. On **same-widget re-target** (handle moves between fields in the same widget), the toolbar snaps to the new field's position instantly with no fade. The previous editor's teardown is followed immediately by the new editor's mount with no intervening preview re-render to bridge; a fade pair would feel sluggish at this rate of switching.

B11. On re-target, the previous editor's debounced raw-config save (if any) is flushed before the new editor's handle is bound to the toolbar.

B12. Clicking the toolbar, the text-style menu, any color/highlight popover, the link popover, the heroicon picker, or the overflow menu **does not** cause the toolbar to disappear, regardless of any focus or blur events that might fire incidentally.

B13. The toolbar's appear/disappear/re-target visibility is governed entirely by §A and this section; no other code path may show or hide it.

### C. Placement and tracking

C1. The toolbar's anchor is the **field** (the `[data-config-key]` host element Quill is mounted in). Not the widget, not the caret, not the Quill selection.

C2. Default position: above the field, 8px gap between the bar's bottom edge and the field's top edge, with the bar's left edge aligned to the field's left edge.

C3. If the bar's natural top in default position is less than 8px below the canvas top (or the sticky page-builder header bottom, whichever is greater), the bar flips below the field: 8px gap between the bar's top edge and the field's bottom edge, same horizontal alignment.

C4. If the bar's natural right edge would exceed the canvas right edge minus 8px, the bar shifts left until its right edge sits at canvas-right minus 8px.

C5. After C4 clamping, if the bar's left edge is less than canvas-left plus 8px, the bar's left edge is set to canvas-left plus 8px (the bar may extend horizontally beyond the field in this case).

C6. The canvas right edge for placement purposes is `inspectorPanel.left - 8px` when the Inspector is open; otherwise `viewport.right - 8px`.

C7. On every scroll event affecting the canvas, the bar's vertical position is recomputed as `top = max(viewportTop + 8px, fieldRect.top - barHeight - 8px)` (or the analogous form when flipped below).

C8. While the field's top is above the viewport top and the field's bottom is below the viewport top (field partially scrolled out at the top), the bar pins to `viewportTop + 8px`. Horizontal anchoring (C2/C4/C5) is unchanged.

C9. When the field's bottom is above the viewport top (field entirely above viewport), the bar hides itself; the handle remains non-null.

C10. When the field's top is below the viewport bottom (field entirely below viewport), the bar hides itself; the handle remains non-null.

C11. When a field that was hidden under C9/C10 returns to the viewport (any portion visible), the bar reappears at its computed position.

C12. The bar's position is recomputed on: throttled scroll events (next animation frame), window `resize`, viewport-mode switch (desktop / tablet / mobile preview), and `ResizeObserver` callbacks for the field's host element.

C13. The bar's stacking context places it above all preview content and the Inspector header, and below Filament modals, Filament dropdowns, and any global admin chrome.

C14. While a handle is non-null, the widget containing `handle.hostEl` has its `.preview-region__handle` (drag-grip) and `.preview-region__edit` (Edit affordance) rendered with `opacity: 0` and `pointer-events: none`. They restore to their normal hover/selected behavior when the handle clears.

C15. The toolbar element is rendered into a fixed-position layer attached to the page-builder app root, not into any preview HTML. It never participates in `v-html` swaps.

### D. Selection preservation and focus

D1. Every toolbar control (buttons, dropdown triggers, popover trigger buttons) handles `mousedown` with `event.preventDefault()` so focus does not leave the editor.

D2. Toolbar buttons do not call `.focus()` on themselves on click.

D3. Activating any toolbar control via click or via the toolbar's own keyboard shortcut (Alt+F10 + Enter; §K) does not collapse, shift, or otherwise alter the Quill selection.

D4. **Pointer interactions** (mouse, touch) on toolbar controls never transfer focus away from the editor; the controls use `mousedown.preventDefault()` to enforce this (D1).

D4a. The one exception under D4 is the link popover's URL field: when the link popover opens via a pointer-driven action, focus moves into the URL field and the current Quill range is captured into a saved variable. Closing the popover (Confirm, Cancel, Escape, or click-outside) restores the saved range as the current Quill selection and returns focus to the editor.

D4b. **Keyboard-driven** focus transfers — Alt+F10 entry to the toolbar (K9), and Tab / arrow navigation within the toolbar (K5–K7) — are explicit, intentional, and not governed by D4. They are specified in §K; D4 governs only pointer interactions.

D5. The color popover, highlight popover, text-style menu, heroicon picker, and overflow menu do not take focus from the editor. The editor's selection remains the active selection while they are open.

D6. While the link popover is open: all other toolbar buttons render at 40% opacity, set `aria-disabled="true"`, and ignore click events.

D7. While any toolbar-owned popover is open and would otherwise consume a keyboard shortcut (e.g., the link popover capturing Cmd+K), that shortcut is consumed by the popover, not by Quill.

D8. Toolbar buttons do not steal keyboard focus from the editor under any circumstance other than D4.

### E. Format-state reflection

E1. The toolbar subscribes to `handle.quill.on('editor-change', ...)` while bound, and recomputes the visible state of all controls on every event.

E2. State recomputation happens synchronously within the `editor-change` handler. The toolbar must not visibly lag the caret.

E3. Active states update regardless of the source of the format change: mouse click on a toolbar button, Quill keyboard shortcut, paste, programmatic application, drag-and-drop.

E4. **Inline mark toggles** (Bold, Italic, Underline, Strikethrough): for a selection range, read `quill.getFormat(range)`:
  - If the relevant format key is uniformly truthy across every position in the range, the toggle renders **active**.
  - If the range contains positions with and without the format, the toggle renders **mixed**.
  - Otherwise, the toggle renders **default** (inactive).

E5. For collapsed selections (caret only), the toggle's active state reflects Quill's pending format at the caret (i.e. `quill.getFormat()` at the caret position). The next typed character will receive that format.

E6. **List buttons** (Bulleted, Numbered) and **Blockquote**: each renders active if every block intersected by the selection has the corresponding block format. Mixed renders if blocks intersected by the selection have a mix of that format and other formats.

E7. **Alignment buttons**: only one of the four (or none) renders active at a time within the alignment group. A button renders active if every block intersected by the selection has that alignment value. When the selection spans blocks of different alignments, no alignment button renders active; the mixed state does not apply to alignment.

E8. **Text-style dropdown closed label**:
  - Uniform Paragraph (no header format): "Paragraph".
  - Uniform Heading N: "Heading 1" … "Heading 6".
  - Selection spans blocks of different formats: "Mixed".

E9. **Text-style dropdown menu** (open): the row corresponding to the selection's uniform block format displays a `check` glyph at the right. Under "Mixed", no row displays the `check`.

E10. **Text color button**: the underbar beneath the icon displays the uniform `color` value of the selection. If the selection contains mixed colors, the underbar displays a checker pattern. If no color is applied, the underbar is empty (matches bar background).

E11. **Highlight button**: same as E10 but for the `background` format.

E12. **Link button**: renders active when the selection is contained within (or fully covers) a link. The button's aria-label switches from "Insert link" to "Edit link" while in this state.

E13. **Clear formatting button**: renders disabled when the selection is collapsed; otherwise default.

E14. State reflection happens for both `text-change` and `selection-change` events delivered through `editor-change`.

### F. Per-control behavior

#### F1. Text-style dropdown

F1.1. Closed-state click toggles the menu open/closed.

F1.2. Open menu lists: Paragraph, Heading 1, Heading 2, Heading 3, Heading 4, Heading 5, Heading 6, in that order.

F1.3. Selecting "Paragraph" applies `quill.format('header', false, 'user')` to the current range.

F1.4. Selecting "Heading N" applies `quill.format('header', N, 'user')` to the current range.

F1.5. The menu closes on selection, Escape, or click outside.

F1.6. The selection in the editor is preserved across opening, selecting, and closing the menu (D5).

#### F2. Inline marks

F2.1. Bold click: `quill.format('bold', !currentlyActive, 'user')` — toggle.

F2.2. Italic click: `quill.format('italic', !currentlyActive, 'user')`.

F2.3. Underline click: `quill.format('underline', !currentlyActive, 'user')`.

F2.4. Strikethrough click: `quill.format('strike', !currentlyActive, 'user')`.

F2.5. When the toggle is in mixed state, clicking applies the format to the entire range (normalizing to all-on), regardless of which sub-runs already have it.

#### F3. Block structure

F3.1. Bulleted-list click: toggles `quill.format('list', 'bullet', 'user')` ↔ `quill.format('list', false, 'user')` on every block in the selection. If any block in the selection is not already a bulleted list, the action applies bulleted-list to all selected blocks; if every block is already bulleted-list, the action removes the list format.

F3.2. Numbered-list click: same as F3.1 but with `'ordered'`.

F3.3. Blockquote click: toggles `quill.format('blockquote', !currentlyActive, 'user')` on every block in the selection.

#### F4. Link

F4.1. Click opens the link popover (§G).

F4.2. Keyboard shortcut Cmd/Ctrl+K opens the link popover.

F4.3. If the selection is collapsed and not inside an existing link, the popover opens in "insert" mode; the Link-text field defaults to empty.

F4.4. If the selection is non-collapsed and not inside an existing link, the popover opens in "insert" mode; the Link-text field is pre-populated with the selected text.

F4.5. If the selection is inside an existing link (or fully covers one), the popover opens in "edit" mode; URL and Open-in-new-tab fields are pre-populated from the existing link; Link-text is pre-populated with the link's visible text; the Remove button is visible.

F4.6. On Save in "insert" mode: the captured range receives the link format (`quill.format('link', url, 'user')`). If Link-text differs from the captured range's text content, the range's text is replaced with Link-text and the link format applied to the replaced range.

F4.7. On Save in "edit" mode: the existing link's URL, target, and visible text are updated as edited.

F4.8. On Remove: the link format is stripped from the captured range; the visible text remains.

F4.9. The "Open in new tab" checkbox controls whether the link is saved with `target="_blank" rel="noopener noreferrer"`. The sanitizer must permit these attributes (verified separately as part of the parity check).

#### F5. Alignment

F5.1. Each alignment button applies its alignment to every block intersected by the current selection.

F5.2. Align left: `quill.format('align', '', 'user')` (Quill's default; empty string).

F5.3. Align center: `quill.format('align', 'center', 'user')`.

F5.4. Align right: `quill.format('align', 'right', 'user')`.

F5.5. Justify: `quill.format('align', 'justify', 'user')`.

F5.6. Clicking the already-active alignment button does not change state (alignment is exclusive within the group; there is no "unalign").

#### F6. Color and highlight

F6.1. Text-color click opens the color popover (§H) configured to apply to the `color` format.

F6.2. Highlight click opens the color popover configured to apply to the `background` format.

F6.3. Selecting a theme color or swatch applies the corresponding format with the chosen value via `quill.format(formatKey, hexValue, 'user')` and closes the popover.

F6.4. Selecting "No color" applies `quill.format(formatKey, false, 'user')` (removes the format) and closes the popover.

F6.5. The popover closes on selection, Escape, or click outside the popover (and outside the editor).

#### F7. Image and heroicon

F7.1. Image click opens a hidden `<input type="file">` scoped to image MIME types (per §4.11).

F7.2. On file selection, the image uploads via the existing `store.inlineImageUploadUrl` endpoint with the model context from the current widget.

F7.3. While the upload is in flight, the Image button is disabled and shows a 12px inline spinner overlaying the icon.

F7.4. On upload success, the returned URL is inserted via `quill.insertEmbed(range.index, 'image', url, 'user')` at the captured range index; the caret moves to immediately after the image.

F7.5. On upload failure, an inline error toast appears below the bar for 4 seconds: red background `#dc2626`, white text, 13px Inter, content "Image upload failed." The Quill selection is unchanged.

F7.6. The image-insert path goes through the editor's HTML buffer (Quill's own document model); the rendered preview DOM is never serialized. This matches the existing Inspector `RichTextField` path.

F7.7. Heroicon click opens the existing `heroicon-picker.js` popover. On selection, `quill.insertEmbed(range.index, 'heroicon', icon, Quill.sources.USER)` is called. The picker UI is unchanged.

#### F8. Clear formatting

F8.1. Click removes inline marks (bold, italic, underline, strike, color, background, link) from the current selection via Quill's `removeFormat` (`quill.removeFormat(range.index, range.length, 'user')`).

F8.2. Block format (header, list, blockquote, align) is **not** affected by this action.

F8.3. The button is disabled when the selection is collapsed.

### G. Link popover

G1. The popover opens anchored under the Link button (flips above if no room) at 320px wide. Background, border, shadow per §4.3.

G2. Field stacking order, top to bottom: URL, "Or pick a page" combobox, Link text, "Open in new tab" checkbox, action buttons row.

G3. Opening captures the current Quill range and stores it as the **saved range** for the duration the popover is open.

G4. The URL field receives focus on open, with its existing content (if any) selected for replacement.

G5. The Page picker is a combobox listing entries from `store.pages`, each row showing the page's title and URL. Filtering is by substring match on title and URL. Selection is by click or by arrow-key navigation + Enter while the combobox has focus.

G6. Selecting a page in the picker populates the URL field with that page's URL and visibly marks the picker as showing that page; the URL field is not disabled.

G7. Manually editing the URL field after a page was selected reverts the picker to its empty state (the page selection is cleared); the URL field's value is what gets saved.

G8. The Link-text field is pre-populated per F4.4 / F4.5 and is editable independently.

G9. "Open in new tab" checkbox is unchecked by default; in edit mode, reflects the existing link's `target` attribute.

G10. The button row contains: **Remove** (left, only visible in edit mode), **Cancel** (right, secondary), **Save** (right, primary).

G11. Save is enabled when the URL field has a non-empty value; otherwise disabled.

G12. Pressing Enter in the URL field or Link-text field is equivalent to Save (if Save is enabled). Pressing Enter in the Page picker selects the highlighted page; if no page is highlighted, Enter is a no-op.

G13. Escape closes the popover (equivalent to Cancel).

G14. Clicking outside the popover and outside the editor closes the popover (equivalent to Cancel).

G15. Focus on close: returns to the editor, with the saved range restored as the current Quill selection.

G16. While the popover is open, other toolbar buttons are visually dimmed and inert per D6.

G17. The URL field's value is validated client-side only against being non-empty; full URL validation is the sanitizer's responsibility on save.

### H. Color and highlight popovers

H1. Both popovers reuse `ColorPicker.vue` in `panelOnly` mode; the toolbar provides the popover frame and positions it.

H2. Popover width: 240px. Anchored under the triggering button, flips above if no room.

H3. The popover does not take focus from the editor (D5). The Quill selection remains the active selection.

H4. Selecting a swatch applies the corresponding format via `quill.format` (F6.3) and closes the popover.

H5. Selecting "No color" removes the format (F6.4) and closes the popover.

H6. Escape closes the popover (no change applied).

H7. Click outside the popover and outside the editor closes the popover (no change applied).

H8. Re-opening after a previous close starts fresh; no persistent state is retained between opens.

### I. Empty regions and plain-text regions

I1. A rich-text field with no content can still be activated by clicking; Quill mounts, the toolbar appears.

I2. In an empty field, the toolbar's text-style closed label shows "Paragraph" (Quill's default block format).

I3. The existing per-field empty-slot "ghost label" (CSS from session 304) remains; it disappears on the first character of input, as it does today.

I4. Plain-text fields (`data-config-type="text"`) do not mount Quill and do not show the toolbar. The absence of the toolbar is itself the cue that the region is plain text.

I5. No additional affordance, badge, tooltip, or cursor change indicates that a plain-text region is plain.

### J. Responsive collapse and overflow

J1. The toolbar fits its natural width when the available width is sufficient (no collapse).

J2. Available width is the width of the canvas (per C6).

J3. As available width shrinks, groups collapse from right to left into an overflow menu, in this order:
  - **Step 1**: Clear formatting (group 8) → overflow.
  - **Step 2**: Image + Heroicon (group 7) → overflow.
  - **Step 3**: Color + Highlight (group 6) → overflow.
  - **Step 4**: Alignment (group 5) → overflow.

J4. The floor (visible-on-bar minimum) is: Text style, B/I/U/S, Lists + Blockquote, Link, plus the overflow `⋯` button.

J5. Below the floor (available width insufficient for even the floor on one row), the bar wraps to two rows. Wrapping occurs as a normal flex layout; nothing is hidden further.

J6. The overflow `⋯` button is visible whenever any group is in overflow; it is hidden when no group is in overflow.

J7. The overflow menu groups items using the same group order as the bar, with section headers per §4.14.

J8. Items in the overflow menu reflect the same active / mixed / disabled state they would if visible on the bar.

J9. The collapse step is determined by the bar's natural fit, measured on every reflow trigger (C12).

J10. Group movement into and out of overflow is instantaneous; there is no animation of items shifting between bar and overflow menu.

J11. The Text-style dropdown, the inline-mark group, the block-structure group, and the Link button never enter the overflow menu under any width.

### K. Keyboard and accessibility

K1. The toolbar root element has `role="toolbar"` and `aria-label="Text formatting"`.

K2. Each button has an explicit `aria-label` matching §4.7.

K3. Toggle buttons (Bold, Italic, Underline, Strikethrough, lists, blockquote, alignment) expose state via `aria-pressed="true" | "false" | "mixed"` mirroring §E.

K4. The Link button uses `aria-pressed="true"` when the selection is inside a link; otherwise omits the attribute.

K5. The toolbar uses a roving-tabindex pattern: exactly one control inside the bar has `tabindex="0"` at a time; all others have `tabindex="-1"`.

K6. Arrow keys (Left/Right) move the roving tabindex between buttons within the bar, skipping disabled controls. Home/End jump to first/last enabled control.

K7. Arrow Up/Down inside a dropdown menu (text-style menu, overflow menu) move the highlighted row.

K8. Enter or Space activates the focused control.

K9. **Toolbar entry shortcut**: Alt+F10, while the active editor has focus, moves focus to the first enabled control on the toolbar. The Quill selection is preserved.

K10. Escape, while focus is inside the toolbar (and no popover is open), returns focus to the editor with the original selection preserved.

K11. Escape, while a popover is open, closes the popover (per F1.5, G13, H6, F7.7 picker behavior, and overflow menu behavior).

K12. Quill's native keyboard shortcuts (Cmd/Ctrl + B, I, U, K, Z, Y, Tab for list indent, Shift+Tab for outdent) continue to work and are reflected in the toolbar's active states (E14).

K13. Focus-visible state (§4.5) renders distinctly from hover so keyboard users see clear focus indication.

K14. The toolbar does not announce its appearance via `aria-live` or any other live-region mechanism. Discoverability for screen-reader users is via Alt+F10 (K9).

K15. All icon colors meet WCAG AA contrast (≥ 3:1 for UI components, ≥ 4.5:1 for any text on the bar) against the bar's background.

K16. The active-pill background `#4f46e5` meets ≥ 3:1 contrast against the bar background `#1f2937`; the icon `#ffffff` on the active pill meets ≥ 4.5:1 against `#4f46e5`.

K17. `prefers-reduced-motion: reduce` disables: the appear/disappear/re-target fades (§B3, B8, B9), any popover open/close transitions, and any pointer/hover micro-animations. State changes happen instantly.

K18. The color popover's keyboard support is: arrow keys navigate swatches in a 2-D grid (within Theme colors, within My swatches), Enter/Space applies the focused swatch, Tab moves between groups (Theme colors → My swatches → Custom hex), Escape closes. This is a small enhancement to the existing `ColorPicker.vue` (§6.1).

K19. The toolbar does not implement touch-specific affordances. Mouse + keyboard are the supported input modes.

K20. The toolbar does not implement a "menu" pattern (Cmd+F6 / F6) for sub-region navigation; the roving-tabindex within the bar plus Alt+F10 entry are sufficient.

### L. Data flow

L1. Every format change applied by the toolbar passes through Quill's `format` / `formatText` / `removeFormat` / `insertEmbed` API with `'user'` as the source argument.

L2. Quill emits a `text-change` event with source `'user'`, which the inline-editing layer's existing handler treats identically to a user keystroke: it writes the editor's `quill.root.innerHTML` to `store.updateLocalConfigPath(widgetId, configPath, newHtml)` on a debounce.

L3. Toolbar-driven formatting writes the raw HTML produced by Quill; no rendered-DOM serialization is performed by the toolbar (§2 and the existing inline-edit raw-write contract).

L4. The debounced save flow already in place (§304 Session A) is unchanged. The toolbar adds no new save path.

L5. `store.inlineActiveWidgetId` continues to suppress the post-save preview-refresh echo while the handle is non-null, exactly as it does today.

L6. On the post-blur reconcile (which destroys Quill and clears the handle), the toolbar disappears (per B5) before the new preview HTML lands.

L7. Sanitization of the saved HTML is unchanged — `PageWidget::saving` applies its existing recursive sanitization. The toolbar's output formats must be a subset of what the sanitizer keeps; any new format that fails sanitization is a bug in either the format choice or the sanitizer, not in the toolbar.

L8. The toolbar reads no data from the network and writes no data directly. All persistence flows through Quill → existing inline-edit layer → existing store path.

### M. Non-goals (must-NOT rules)

M1. The toolbar MUST NOT be created more than once in the DOM at any time.

M2. The toolbar MUST NOT be created or destroyed at runtime; it is created once at page-builder mount and destroyed only when the page builder itself unmounts.

M3. The toolbar MUST NOT hold a reference to a Quill instance beyond the active-editor handle's lifetime.

M4. The toolbar MUST NOT take focus away from the editor except via the link popover's URL field (D4).

M5. The toolbar MUST NOT collapse, shift, or alter the Quill selection on any interaction other than D4 (link popover capture/restore).

M6. The toolbar MUST NOT serialize rendered preview DOM into the saved config. All writes go through Quill's own HTML buffer (L3).

M7. The toolbar MUST NOT introduce a second render path, a second editor, or a second sanitizer.

M8. The toolbar MUST NOT include a font-family control. Font is controlled by the theme.

M9. The toolbar MUST NOT appear on plain-text fields (I4) or on widgets that are not inline-eligible.

M10. The toolbar MUST NOT depend on the surrounding admin's light/dark mode. It is always dark (§4.1).

M11. The toolbar MUST NOT be present in any preview HTML (`v-html` blocks). It lives in a fixed-position layer at the app root (C15).

M12. The toolbar MUST NOT block the user from interacting with other widgets, other parts of the canvas, or the Inspector. It is chrome, not a modal.

M13. The toolbar MUST NOT introduce a new color-picker component. It reuses `ColorPicker.vue` in `panelOnly` mode.

M14. The toolbar MUST NOT introduce a new heroicon picker. It reuses the existing `heroicon-picker.js`.

M15. The toolbar MUST NOT permit Tier-B exempt fields to be inline-edited; the toolbar appears only on fields cleared by the existing inline-eligibility / annotation gate.

M16. The toolbar MUST NOT introduce a "view HTML source" mode, a word-count display, or a reading-time estimate (these appeared in reference image 1 but are out of scope and conflict with the raw-config / token-bearing reality).

---

## 6. Companion changes (engineering scope outside the toolbar itself)

The toolbar relies on the following changes elsewhere in the codebase. Each is small in isolation, but together they represent the engineering scope around the toolbar — enumerated concretely here so they are not surprises mid-build.

### 6.1 `ColorPicker.vue`

- **Existing, used unchanged:** the component already exposes a `panelOnly` prop (defaults to `false`). The toolbar's color and highlight popovers use this mode as-is; no new mode is introduced.
- **New: arrow-key grid navigation.** Within each swatch group (Theme colors, My swatches), Left/Right/Up/Down arrows move keyboard focus across the swatches as a 2-D grid; Enter or Space applies the focused swatch; Tab moves between groups (Theme colors → My swatches → Custom hex). Escape close is already implemented and is unchanged.

### 6.2 HTML sanitizer

- The sanitizer applied in `PageWidget::saving` (and the recursive repeater-row pass added in session 304) must permit `target` and `rel` attributes on `<a>` tags. Without this, the link popover's "Open in new tab" checkbox cannot persist — the attributes are silently stripped on save and the link loses its target on the next render.
- Permitted values on `<a>`: `target="_blank"` and `rel="noopener noreferrer"`. The toolbar emits these literally via Quill's link format; the sanitizer must keep them.
- This is a one-time sanitizer-rule addition, not a per-link decision.

### 6.3 Page-builder bootstrap data

Two additions to the data the Vue layer receives via `WidgetResource` / the page-builder mount payload:

- **Resolved theme font stacks.** The heading-bucket and body-bucket font-family CSS strings, resolved by `TypographyResolver`, exposed as `theme_heading_family` and `theme_body_family` on the bootstrap payload. The text-style menu (§4.8) renders each row in the theme's actual family using these values. Without them, the menu falls back to the bar's UI font and the WYSIWYG-preview promise of the spec is not met.
- **Resolved page URL per entry in `pages`.** Each `PageRef` in the bootstrap data must include the page's currently-resolved URL alongside its existing fields (title, id, etc.). The link popover's page picker (§4.10) populates the URL field from this value when a page is selected. Verify whether `PageRef` already carries this; surface as a real addition if not.

### 6.4 Lucide icon delivery

Lucide is a new dependency. **Decision: install `lucide-vue-next` as an npm dependency** and import each icon via tree-shakeable named imports.

Rationale:
- The page-builder Vue app builds through Vite; tree-shakeable per-icon imports keep bundle size minimal (only the ~18 toolbar icons are bundled).
- Avoids vendored-SVGs-drift-from-upstream maintenance.
- `lucide-vue-next` is the official Vue 3 package, semver-stable, widely used.

Alternative considered and rejected: vendor the ~18 SVGs into the repo (similar to how Heroicons is delivered via `blade-ui-kit/blade-heroicons`). Rejected because (a) the vendored set drifts from upstream over time, (b) tree-shaking already gives the same bundle-size benefit without manual sync, (c) it forks the icon-set update path for no win.

Heroicons remains the icon set for the Blade/admin chrome and any content-level uses elsewhere; Lucide is scoped to the inline formatting toolbar's button glyphs.

---

## 7. Reconciliation with visual references

The visual references shared during design conflicted with the hard constraints in a few places. The constraint wins; this section records the conflict and the resolution.

| Reference element | Conflict | Resolution |
|-------------------|----------|------------|
| Image 1 "Visual / Text" source toggle | No HTML source mode is supported; raw vs rendered separation is structural, not user-controllable | Out — not implemented |
| Image 1 word count / reading time footer | Token-bearing raw content makes word count meaningless during edit | Out — not implemented |
| Google Docs "Title / Subtitle" entries in style menu | Quill has no "Title" or "Subtitle" block format | Replaced by Paragraph + H1–H6 (E8, F1) |
| Image 1 / Image 3 attach (paperclip) icon | "Attach" is not a Quill operation; treated as image insert | Subsumed under the Image button (F7) |
| Image 1 dark theme over light reference content | Some references show light bars; the chosen theme is always dark | Dark locked (§4.1) |

---

## 8. Visual rendering checklist

When the mockup images are produced (in a design tool, downstream of this spec), they must match every value in §4. The mockups must include all 10 states listed in §4.15. Any deviation from this spec discovered during mockup production is a spec issue, not a mockup issue, and must be resolved by amending this document first.

---

## 9. Known follow-ups (out of scope for this work)

These are intentionally not built. Each is a real, named decision deferred to a future session.

- **Internal page-reference link storage**: links to site pages currently store the page's URL at the time of selection. If the page's slug changes, the link does not auto-follow. A future enhancement could store an internal-link token (e.g., `{{page:id}}`) and resolve at render — this is an architectural extension touching the token pipeline and is its own scoped session.
- **Subscript / superscript / indent / outdent buttons**: not in the existing Inspector bar; not added here. If end-user demand surfaces them, add as a separate scoped change.
- **Last-used color split-button** for the color/highlight controls (Google Docs–style chevron + quick-apply). Not in v1.
- **Touch-specific affordances** (larger hit targets, long-press menus, mobile bar layout). Mouse + keyboard only.

---

## 10. Acceptance

This spec is the implementation contract. A build is conformant when every rule in §5 passes its testable form, every visual in §4 matches the mockup output, every "MUST NOT" rule in §5.M holds, and the companion changes in §6 are made. The deferred follow-ups in §9 are not requirements to satisfy — they are explicit deferrals.

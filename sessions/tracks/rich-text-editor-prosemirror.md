# Track: Rich-Text Editor (ProseMirror Migration)

The deferred arc to replace the rich-text editor (Quill 2) with **ProseMirror**, unlocking true inline-in-prose tables and arbitrary-structure fidelity that Quill structurally cannot provide. Scoped and deferred during session-349 planning (2026-06-09); queued as a post-launch fast-follow.

This doc carries:

- **Status snapshot** — where the track stands, what triggers it.
- **The forcing analysis** — why Quill is the ceiling and why ProseMirror.
- **The scope map + forward plan** — what the migration touches, the ~6–8-session sequence.
- **Key decisions & insights** — the load-bearing calls from the 348/349-planning analysis (carry them forward verbatim; don't re-derive).

---

## Status snapshot

**Last update:** 2026-06-09 (scoped + deferred during 349 planning).

**Status: DEFERRED — queued fast-follow, not started.** No sessions scheduled. Decision (349-planning conversation): ship to launch on Quill; this migration is a fast-follow, pulled earlier only if pre-launch feedback demands authoring/pasting tables in flowing prose. **Safe to defer because rich text is stored as sanitized HTML** — the swap is a non-destructive editor replacement over the same stored content, addable in a focused track whenever wanted. Owner estimate: ~2 days of focused work once the parity harness exists.

**Trigger:** post-launch fast-follow, OR earlier on negative pre-launch feedback about table / long-form authoring.

**Down-payment landed ✅:** session **349** shipped a block-level Table widget backed by an *embedded* `prosemirror-tables` editor, isolated to that one widget. It solved the table piece, landed the team's first ProseMirror integration, and proved the patterns this track reuses: HTML round-trip (HTML→doc on open, doc→HTML on save, no PM-JSON in storage), the `HtmlSanitizer` cell-attribute extension (validated `colspan`/`rowspan`), a minimal isolated schema, and a custom NodeView (the per-column `<colgroup>`). The track now starts with ProseMirror **proven in the codebase, not cold** — see `sessions/349. Table Widget — Log.md`.

---

## The forcing analysis (why this track exists)

**Quill 2 is a constrained structured editor; tables and arbitrary-HTML fidelity are a permanent design ceiling, not a config gap.** Proof in-codebase: [useInlineEdit.ts:194-208](../../resources/js/page-builder-vue/composables/useInlineEdit.ts#L194-L208) documents that Quill strips any markup that isn't a registered blot on its normalization pass — a `<table>` has no blot, so Quill deletes it. The real customer need — legal documents, cookie/privacy policies, pasted tabular data — is authoring and **pasting** tables *inside flowing rich text*, with formatting control and clean export. That's exactly what Quill can't do and what this track delivers.

**Editor evaluation (349 planning) — ProseMirror chosen.** `prosemirror-tables` is the mature, MIT engine for content tables (cell-range selection, merge/split, column resize, HTML-table paste-normalization). ProseMirror is JSON-native, framework-agnostic (mounts into the Vue page-builder like Quill does today), its **schema is itself a security allow-list**, and existing Quill HTML ingests natively. Rejected, with reasons:

- **TipTap** — its table extension is free/MIT, but the project has a strong commercial pull (Pro/Cloud); owner declined building on it. (ProseMirror is the engine under TipTap anyway — the table strength is `prosemirror-tables`.)
- **CKEditor 5** — best-in-class table UX, but GPL-or-commercial; the copyleft is a problem for a proprietary product.
- **Lexical** — MIT, good tables, but React-first; Vue support is community bindings — a second-class risk for a load-bearing surface in a Vue 3 app.
- **Editor.js** — best clean-JSON / block-model fit (echoes the page-builder), but its table tool is weak (no merge/colspan) and paste fidelity is poor; a custom table block = rebuilding `prosemirror-tables` from scratch.
- **Data-grid packages** (Handsontable / AG Grid / Tabulator / Jspreadsheet) — wrong abstraction: they edit *datasets* and output data, not document tables; the good ones are commercial. Content-table editing only lives well *inside* document editors because it's a document-model problem.

---

## The key scope-reducer: store HTML, not ProseMirror-JSON

The decision that keeps this bounded and makes the deferral non-destructive: **keep storing sanitized HTML** (what's stored today). ProseMirror parses HTML in and serializes HTML out natively. Consequences:

- **Back-end virtually untouched** — all ~8 `HtmlSanitizer` boundaries (`CollectionItem`, `EmailTemplate`, `Event`, `Note`, `PageWidget`, `SiteSetting`, the custom-fields trait, the importer) keep working; the sanitizer stays the security gate (extended for table attributes).
- **No content migration** — existing Quill-authored HTML parses straight into ProseMirror.
- **No bundle-format change, no contract bump** — the export bundle carries rich text as HTML strings; that doesn't move.

This makes the migration **front-end-dominated** and the deferral **reversible/non-destructive**.

---

## Surface map (what the migration touches)

**Front-end (the bulk):**
- Three editor surfaces — inline ([useInlineEdit.ts](../../resources/js/page-builder-vue/composables/useInlineEdit.ts)), Inspector ([RichTextField.vue](../../resources/js/page-builder-vue/components/fields/RichTextField.vue)), admin Filament ([quill-editor.js](../../resources/js/admin/quill-editor.js) + `quill-editor.blade.php` + `QuillEditor.php`).
- The 347 inline-format toolbar layer ([InlineFormatToolbar.vue](../../resources/js/page-builder-vue/components/InlineFormatToolbar.vue) + 4 composables + 3 sub-components) — **~30 direct Quill-API call sites.** The UI shell survives; the format commands + active-state get **rewritten** against ProseMirror's different command/state model (the toolbar "rethink").
- Custom pieces to re-home: the heroicon blot ([heroicon-blot.js](../../resources/js/admin/heroicon-blot.js)) → a PM node; the color mark; link target/rel; image-upload-with-dedup; keyboard shortcuts; a11y titles.
- Build: ProseMirror npm packages via Vite, replacing the Quill CDN global — **admin-only bundle, zero public-bundle impact** (the public site renders sanitized HTML, no editor).

**Back-end (small):** extend `HtmlSanitizer` for table cell attributes; a little `.np-table` public-render CSS.

**Tests:** the Pest layer mostly **survives** (it tests stored HTML + render + the sanitizer, all stable — add table cases). The impact concentrates in **Playwright** — the editor-UI specs (`inline-editing-foundation`, `inline-editing-phase2`, the 347 `inline-formatting-toolbar`, `memos-trix-to-quill`, `full-width-matrix`, `text-widget-border`) need reworking — plus a new tables spec.

---

## Forward plan (~6–8 sessions, gated by a POC)

1. **ProseMirror POC / de-risk.** Stand PM up on the *Inspector* field (simplest surface). Prove existing Quill HTML loads + round-trips through the sanitizer; lock the HTML-storage decision; establish the Vite build; **build the parity oracle** (a corpus of real stored rich-text run old-vs-new with byte comparison + formatting-permutation Playwright). The gate before committing the rest.
2–3. **Inline engine + toolbar core.** Swap `useInlineEdit`'s engine; rebuild the 347 toolbar's core formatting (bold/italic/underline/strike, headings, lists, blockquote, align) against PM commands + active-state. The biggest chunk — likely spills to 2.
4. **Custom features.** Link popover (target/rel), image + dedup, color mark, heroicon node, keyboard shortcuts.
5. **Admin Filament editor.** The Alpine `quill-editor.js` surface → PM, preserving image-dedup + heroicon. *(Separable scope lever — could stay on Quill longer at the cost of temporary divergence.)*
6–7. **Inline-in-prose tables — the true E15 intent.** `prosemirror-tables` in the prose editor: a table authored mid-flow inside a Text widget's rich text. (Session 349 already proved the table piece in isolation.)
8. **Tests + content-compat + Quill removal.** Rework the Playwright specs, verify existing content across all surfaces, rip out Quill (CDN, heroicon-blot, toolbar-titles), final pass.

The two variables driving 6-vs-8: the toolbar rethink (1071 LOC, paradigm shift) and how far inline-table appearance controls go.

---

## Key decisions & insights (carry forward; don't re-derive)

- **Store HTML, not PM-JSON** — the non-destructive-deferral enabler (above). This is the load-bearing call.
- **Autonomy is purchasable via the oracle.** The behavior-preserving *swap* half has a perfect oracle — the old editor (match-the-old-output, mechanically checkable via the parity harnesses: `BuilderPublicRenderParityTest`, the design-group guard, Playwright). So the boring middle can run as widely-gated supervised autonomy *if* the POC front-loads a strong parity harness. The two parts that must stay tightly human-gated: (a) the POC / architecture decisions (no oracle yet, high blast radius — a wrong commit compounds across 6 sessions) and (b) the net-new inline tables (no oracle ever, and the fidelity/feel the owner won't compromise on). Net: not fully autonomous, but the swap-middle can be offloaded to free attention for the POC + table sessions.
- **The 349 widget is the down-payment** — same engine, validates the HTML-round-trip + sanitizer extension + `prosemirror-tables` in a contained sandbox. The track starts warm.
- **Sequencing fork:** full migration (tables at the end, one editor, uniform UX) vs tables-first-on-a-new-surface (faster to the capability, temporary two-editor divergence). Default: full migration — the 349 widget already buys much of the "tables sooner" benefit.

---

## E15 lineage

This track absorbs the *true intent* of release-plan **E15 (Table widget)**, which the plan had already correctly revised (after the 311 standalone-widget attempt was rewound) to "a rich-text-editor *Insert-Table* affordance, inside the editor, not a standalone widget." That inline affordance is impossible on Quill and lands here. Session **349** ships the block-level widget stopgap in the meantime; it is a distinct deliverable, not E15's inline criterion.

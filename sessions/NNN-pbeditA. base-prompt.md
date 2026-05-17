# Base Prompt — Session NNN (Page Builder Inline Editing — Session A: Foundation) · DRAFT FOR REVIEW

> **Draft.** Number deliberately unassigned (placeholder `NNN-pbeditA`). **Critical-path, Beta-1-blocking.** This is **Session A of a two-session split** (A = interaction-model + in-page text + repeater control; **B** = custom formatting toolbar + builder↔public parity + widen-the-gate). A is the **prerequisite for B** — B cannot run until A has shipped. When scheduled, this likely warrants a `release-plan.md` §entry + `session-outlines.md` stub; flag that at scheduling, do not fabricate plan structure here. A may itself split per Rule 11 if it inflates (the phases are ordered to split cleanly).

**Start now.** Execute the reading list below, open the session prompt at `sessions/NNN-pbeditA. Page Builder — Inline Editing Foundation.md`, then begin the work. No confirmation needed.

---

We are about to begin a new session: **NNN. Page Builder — Inline Editing Foundation (Handles, Selection, In-Page Text & Repeater Control)**.

This session has **no execution-order position in `sessions/release-plan.md` yet** — it is an **emergent, critical-path forcing-function session**: beta testers cannot author pages effectively with the current select-the-whole-panel + Inspector-only model. It is **Session A** of a deliberate two-session split. A is independently shippable and useful on its own; B builds strictly on top of it.

This session is **NOT boundary-touching** — no Fleet Manager surface. **CRM contract stays at v2.3.0.** **No DB schema change**: config persistence reuses the existing `PageWidget` save path, and the inline-edit capability gate is **declared in widget code (PHP definition), never the database** (settled decision — see the session prompt's "Decided — capability-gate location"). If implementation appears to need a `widget_types` column, **stop and surface** — the decision is no-migration.

**Session shape:** feature-style, UX-heavy. One-sentence summary: replace whole-panel selection with a top-left hover-in **drag handle** + an explicit top-right **"Edit" affordance** (opens the Inspector), restore **in-page text editing** for the safe widgets behind a code-declared per-node capability gate (with the discoverability spec), and **simplify the column/repeater management** surface (PricingChart canonical) to structural add/remove/reorder. **Out of scope for A, by the split:** the custom rich-text formatting toolbar (Session B) — during A, rich-text *formatting* continues to use the existing Inspector `RichTextField`/Quill as a **documented interim**; A delivers in-place text *entry and editing*, not the contextual formatting UX.

Before doing anything else:

1. Re-read the session templates only if uncertain about a structural detail — stable since 276.
2. Read `sessions/session-outlines.md` — Active-tracks block + Housekeeping Inbox pointer. No forward stub yet (emergent); if one was added at scheduling, it becomes canonical.
3. No `release-plan.md` entry unless added at scheduling. Read Phase E (Demonstrability polish) for adjacent Beta-1 context only.
4. Not part of an active track. No track doc unless created at scheduling.
5. Read `docs/app-reference.md` — the **single render path** (`WidgetRenderer::render()` feeds both public and the builder preview) and the runtime-vs-build-server boundary. The single render path is why the inline-edit token hazard is real and why B's parity tests will be tractable. Read `docs/schema/README.md`; this session adds no schema.
6. Read the leverage surfaces before touching them:
   - `resources/js/page-builder-vue/components/PreviewRegion.vue` — preview HTML via `v-html` behind a transparent click-to-select overlay (`.preview-region__html { pointer-events: none }`). Selection, the handle, the Edit affordance, and the contenteditable activation layer all land here.
   - `resources/js/page-builder-vue/components/PreviewCanvas.vue`, `App.vue`, `stores/editor.ts`, `composables/useDebouncedSave.ts`, `composables/useRefreshPreview.ts` — selection state, `updateLocalConfig()` (the config-mutate→debounced-save path inline editing routes through), and the preview-refresh echo that will clobber a live editor mid-keystroke.
   - The `vuedraggable` config in the preview/region components — it already uses a `handle=` selector (currently the full-surface overlay); repointing it at a real grip is the bulk of the handle work.
   - `resources/js/page-builder-vue/components/InspectorPanel.vue` + `components/fields/RichTextField.vue` — the Inspector keeps **non-text** style/appearance/layout; `RichTextField.vue` is the existing Quill integration and carries a dirty-guard pattern the preview-refresh suppression must mirror. In A, formatting stays here (interim).
   - `resources/js/page-builder-vue/components/fields/RepeaterField.vue` + `RepeaterRowField.vue`, and **PricingChart in full** (`app/Widgets/PricingChart/PricingChartDefinition.php` + `template.blade.php` + `styles.scss`) — the canonical stress test (three-deep nesting + CTA buttons + cross-column `subgrid`/`--pc-attr-rows` alignment).
   - The 6 templates with dormant `data-config-key`/`data-config-type` annotations (the original 137 hooks, never removed): `app/Widgets/TextBlock/template.blade.php:24`, `app/Widgets/Hero/template.blade.php:40`, `app/Widgets/ThreeBuckets/template.blade.php:24,28`, `app/Widgets/EventsListing/template.blade.php:58`, `app/Widgets/DonationForm/template.blade.php:86`. **PricingChart has none** — its anchors are net-new and nested.
   - `public/css/admin.css:677–692` — the dormant `.inline-editable` hover/focus affordance CSS (hand-edited, served directly — no build step). The discoverability spec activates it.
   - The `WidgetRenderer` entry point — **token substitution runs before render**; serializing rendered DOM back to config bakes resolved `{{tokens}}` and destroys templates. Read how tokens resolve so the gate is precise.
   - `app/Models/PageWidget.php` — the `saving` sanitization hook + the config save allow-list; confirm every inline path (`content`, `heading_N`, `body_N`, nested `columns.*.…`) is allow-listed and sanitized.
7. Read the history for context (decision-critical, brief): inline editing existed (137, Livewire), was never removed for cause — dropped by attrition in the 147–152 Vue rewrite. The one design objection on record was a floating toolbar over multiple regions; that constraint is owned by Session B, not A.
8. Open `sessions/NNN-pbeditA. Page Builder — Inline Editing Foundation.md` and read it carefully — it carries the phased plan, the PricingChart stress-test accommodations, the 40-widget safety pass + Tier-B exempt set + guard test, the discoverability spec, and the capability-gate decision (all canonical).
9. Note any drift between prompt and code in a brief work-log entry; proceed unless something requires a decision per the drift/decision-threshold rules.

---

## Starting state inherited from the preceding session

*(Required. Unnumbered; "preceding session" = whatever runs immediately before A. Re-confirm baselines from that session's log at start; adapt to drift silently.)*

- **CRM contract at v2.3.0** — unchanged; A does not touch it. Not boundary-touching.
- **Schema baseline:** no DB schema changes from A. If the capability gate appears to need a `widget_types` column, stop and surface — the settled decision is in-code, no migration.
- **Fast suite green at the preceding session's close baseline** — confirm exact count from that log. New Pest expected (capability-gate eligibility logic; token/data-driven exclusion; sanitization + allow-list coverage of inline paths; the Tier-B exempt-set guard). New Playwright expected (the interaction model; inline-edit round-trips incl. nested; discoverability assertions; the repeater structural control).
- **Playwright suite** — baseline preserved; A adds specs. `feedback_test_runs_not_parallel` applies.
- **Build/version discipline (load-bearing):** bump repo-root `VERSION` to `0.NNN.<iteration>` each iteration; deploy pipeline hard-fails a forgotten/duplicate bump. `public/css/admin.css` is hand-edited/served directly — **no build** for the `.inline-editable` CSS; Vue/SCSS changes need `npm run build` per the base-prompt build rule.
- **Planning state:** if scheduled as a plan entry/stub, that is canonical and this prompt is the delta; otherwise emergent. A creates no plan structure beyond close-gate bookkeeping.
- **Housekeeping inbox** — focused critical-path feature, not a housekeeping batch; do not absorb inbox items.

---

## Process rules for every session

Base-prompt process rules apply in full. Session-specific notes:

- **The token/data-driven gating is mandatory safety, not polish.** Inline editing writes the **raw stored config value at a path**, never serialized rendered DOM. Gated off for any node containing `{{tokens}}` and for data-driven/templated widgets. The gate is declared in widget code (settled).
- **Inline-eligibility is opt-in by template annotation, never schema-type-derived (validated by the 40-widget pass).** A node is inline-editable only if its template explicitly annotates it as display prose. The session prompt's Tier-B exempt set (`gap`, `max_width`, `alt_text`, `image_aspect_ratio`, `branding_text`, `x_label`, `y_label`) must never be annotated; the guard test enforces this.
- **Suppress the preview-refresh echo on the actively-edited node — text edits only.** Mirror the `RichTextField.vue` dirty-guard. **Structural edits (the repeater control) always re-render** — PricingChart's cross-column subgrid alignment recomputes server-side; never refresh-suppress them. Required for in-place editing to function at all. The Quill instance lifecycle (mount/dispose as widgets are (de)selected and as the preview re-renders) is the same problem — solve it together.
- **Discoverability is selection-scoped progressive disclosure (part of Phase 2, not optional).** Editability is never revealed page-wide; the unselected preview stays pixel-clean. Empty slots show muted schema-`label` ghost text; non-empty reuse the `.inline-editable` hover/focus CSS + `text` cursor; the whole slot (incl. padding) is a generous click target.
- **PricingChart is the canonical acceptance test.** Path-addressed multi-node binding, nested-repeater structural editor, per-row structural controls (`emphasize`), buttons split (inline label / structural url+style), empty-field placeholder rendering. A green PricingChart round-trip (text + nested structure + buttons) is the A exit criterion.
- **Formatting is interim in A by design.** Do not build the custom toolbar (that is B). Rich-text formatting during A uses the existing Inspector `RichTextField`. Note the interim awkwardness in the log; do not try to half-build B's toolbar.
- **Playwright is the verification mechanism** for the selection model, handle reorder, inline-edit round-trips, discoverability, and the repeater control — close those loops yourself.
- **Run the fast suite + new Playwright (sequentially) when complete.** Green, every count delta explained.
- **Pause for manual UX testing at session end** (the selection model + in-place editing feel + the repeater control). Announce what's ready, then stop and wait. Do not suggest closing.

---

## ── SESSION CLOSE GATE ──────────────────────────────────────────────────────

**Everything below happens only when the user explicitly says to close.** Do not ask, suggest, or pipeline.

### Phase 1 — Attenuate and prepare next session

After implementation + manual UX testing are complete, the natural successor is **Session B** (`sessions/NNN-pbeditB. *`) — its draft already exists. Refresh B's "Starting state inherited" against what A actually shipped; draft nothing else unless the user names it.

### Phase 2 — Close

When the user says to close: write `sessions/NNN. Page Builder — Inline Editing Foundation — Log.md` per `template-session-log.md` (selection/affordance model; widgets inline-enabled + the code-declared gate shape; token/data-driven exclusions; refresh-suppression + Quill lifecycle; the repeater control; discoverability as shipped; the Tier-B guard; the formatting interim as left for B; new Pest/Playwright; `VERSION` bump; drift; B carry-forward). Update `completed-sessions.md`, archive the previous session's files, commit on the final `session-NNN/N` branch, notify, do not push. Never push to main / force-push / merge to main. If scheduled as a plan entry/track, update those per the standard pattern. Do not begin Session B until the user starts it.

---

## Style rules

Base-prompt style rules apply. Session-specific: iteration commit messages describe the deliverable; PM-level status tone per `memory/feedback_pm_level_tone.md`; no docstrings/comments/type-annotations on code you did not write. **Extend, don't reinvent** — re-activate the dormant `data-config-key` hooks + `.inline-editable` CSS, route through the existing `updateLocalConfig`→debounced-save, reuse the single `WidgetRenderer`, repoint the existing `vuedraggable` handle, simplify (not rebuild) `RepeaterField`. No new editor framework, no new state model, no replacing `v-html`. This is a re-activation + interaction-model + structural-simplification session, not a page-builder rewrite.

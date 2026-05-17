# Base Prompt — Session NNN (Page Builder — In-Page Editing) · DRAFT FOR REVIEW

> **Draft.** This is a review draft, not a runnable prompt yet. Number deliberately unassigned; placeholder token `NNN-pbedit`. This is **critical-path, Beta-1-blocking** work (current page-builder UX does not work well enough for beta testers). Because it is Beta-1-blocking and sizeable, when scheduled it most likely warrants a real `sessions/release-plan.md` §entry + a `sessions/session-outlines.md` stub rather than staying emergent — flag that to the user at scheduling time; do not fabricate plan structure here. It is large enough that it **may split across sessions per Rule 11** (the phasing below is designed to split cleanly).

**Start now.** Execute the reading list below, open the session prompt at `sessions/NNN-pbedit. Page Builder — In-Page Text Editing, Handles & Inline Formatting.md`, then begin the work. No confirmation needed.

---

We are about to begin a new session: **NNN. Page Builder — In-Page Text Editing, Handle-Based Reorder & Inline Formatting Affordance**.

This session has **no execution-order position in `sessions/release-plan.md` yet** — it is an **emergent, critical-path forcing-function session**: beta testers cannot effectively author pages with the current select-the-whole-panel + Inspector-only model, and this is a launch blocker. It does not belong to an existing active track, but it is large enough that it could become its own short track or split into sequential sessions (Rule 11). Its phasing is ordered so the interaction-model rework (Phase 1) and the first safe inline-edit slice (Phase 2) are independently shippable ahead of the harder formatting-affordance design (Phase 3) and the standing parity-test deliverable (Phase 4).

This session is **NOT boundary-touching** — no Fleet Manager surface is in scope. **CRM contract stays at v2.3.0.** No DB schema change is expected: config persistence is unchanged (the existing `PageWidget` config save path is reused), and the per-widget inline-edit capability gate should live on the **widget definition (PHP), not a migration** — confirm at session start; if the only clean place turns out to be `widget_types`, that is a schema change and must be surfaced before proceeding.

**Session shape:** feature-style, UX-heavy, large. It carries one genuinely hard design problem (the inline rich-text **formatting affordance** — controls must exist and must *not* live in the right-hand Inspector when text is edited in place) and one standing-infrastructure deliverable (automated **builder-preview ↔ public-render appearance lock-step** tests, because that parity is not currently guaranteed). One-sentence summary: replace whole-panel selection with a top-left hover-in drag handle + an explicit top-right "Edit" affordance, restore in-page text editing for safe widgets behind a capability gate, solve the inline-formatting-toolbar UX as a first-class problem (Quill-vs-alternative explicitly on the table), and add regular tests that keep the builder preview and the public site in appearance lock-step.

Before doing anything else:

1. Re-read the session templates only if uncertain about a structural detail — stable since 276.
2. Read `sessions/session-outlines.md` — the Active-tracks block and the Housekeeping Inbox pointer. There is no forward stub for this session yet (emergent); if one was added at scheduling, read it — it becomes canonical.
3. There is **no `release-plan.md` entry** for this session unless one was added at scheduling. Read `release-plan.md` Phase E (Demonstrability polish) for adjacent context and the project's Beta-1 framing; this session is in that spirit but is its own item.
4. This session does not belong to an active track. No track doc unless one is created at scheduling.
5. Read `docs/app-reference.md` — specifically the **single render path** (`WidgetRenderer::render()` feeds *both* the public site and the builder preview) and the runtime-vs-build-server boundary. The single render path is the asset that makes the Phase 4 lock-step tests tractable and the inline-edit token hazard real — internalize it before designing.
6. Read `docs/schema/README.md`. This session adds no schema.
7. Read the leverage surfaces before touching them:
   - `resources/js/page-builder-vue/components/PreviewRegion.vue` — renders preview HTML via `v-html` behind a transparent click-to-select overlay; `.preview-region__html { pointer-events: none }`. This is where selection, the handle, the "Edit" affordance, and the contenteditable activation layer all land.
   - `resources/js/page-builder-vue/components/PreviewCanvas.vue`, `App.vue`, `stores/editor.ts` — selection state, `updateLocalConfig()` (the existing config mutate→debounced-save path inline editing must route through), the debounced-save/refresh-preview composables (`useDebouncedSave`, `useRefreshPreview`) — the preview-refresh echo that will clobber a live editor mid-keystroke originates here.
   - The `vuedraggable` config in the preview/region components — it already uses a `handle=` selector (currently the full-surface overlay). Repointing it at a real grip element is the bulk of the handle work.
   - `resources/js/page-builder-vue/components/InspectorPanel.vue` + `components/fields/RichTextField.vue` — the Inspector keeps **non-text** style/appearance/layout; `RichTextField.vue` is the existing Quill integration and carries a dirty-guard pattern the preview-refresh-suppression should mirror.
   - The **6 widget templates that still carry dormant `data-config-key` / `data-config-type` annotations** (the original 137 activation hooks, never removed): `app/Widgets/TextBlock/template.blade.php:24`, `app/Widgets/Hero/template.blade.php:40`, `app/Widgets/ThreeBuckets/template.blade.php:24,28`, `app/Widgets/EventsListing/template.blade.php:58`, `app/Widgets/DonationForm/template.blade.php:86`.
   - `public/css/admin.css:677–692` — the dormant `.inline-editable` / `.inline-editable--text` affordance CSS (hand-edited, served directly — no build step), kept since the 142 CSS audit.
   - `app/Services/...WidgetRenderer` (the render entry point) — **token substitution happens before render**. Serializing rendered DOM back to config would bake resolved `{{tokens}}` and destroy templates. Read how tokens are resolved so the gating rule is precise.
   - `app/Models/PageWidget.php` — the `saving` sanitization hook + the config save allow-list. Confirm every inline-editable key (`content`, `heading_N`, `body_N`, …) is both in the allow-list and sanitized.
8. Read the history (decision-critical): `sessions/archived/137. *` (added inline editing on the old Livewire stack), `sessions/archived/139. *` (de-scoped for sequencing), `sessions/archived/147–152. *` (Vue rewrite that dropped it by attrition). The **one** substantive design objection on record is narrow: a floating rich-text **toolbar over multiple editable regions** caused UX conflict at 137 — plain contenteditable shipped fine. This session deliberately re-opens that exact problem (controls *must* exist) and must solve it *without* repeating the multi-region floating-toolbar failure. Inline editing was **never removed for cause** — there is no lurking "this was a mistake" rationale.
9. Read `sessions/NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard.md` (or its scheduled-number equivalent) — the Phase 4 lock-step tests here are **complementary, not duplicative**: that session's drift guard checks *served bundle ≡ saved settings*; this session's parity tests check *builder-preview render ≡ public render appearance*. Different seam, same anti-drift theme. Coordinate so they don't overlap or contradict; if that session has already shipped, build on its guard rather than re-inventing.
10. Open the session prompt and read it carefully — it carries the phased plan, the canonical UX requirements, the hard open decisions (Quill-vs-alternative is the big one), and the explicit scope fence.
11. Note any drift between the prompt and the actual code in a brief work-log entry; proceed unless something requires a decision per the drift and decision-threshold rules.

---

## Starting state inherited from the preceding session

*(Required section. Unnumbered; "preceding session" = whatever runs immediately before it. Re-confirm baselines against that session's log at start; adapt silently to drift.)*

- **CRM contract at v2.3.0** — unchanged; this session does not touch it. Not boundary-touching.
- **Schema baseline:** no DB schema changes expected from this session. If the capability gate forces a `widget_types` column, that is a schema change — stop and surface before writing the migration.
- **Fast test suite green at the preceding session's close baseline** — confirm the exact count from that log. New Pest expected (capability-gate logic, sanitization/allow-list coverage of inline keys, the parity-test harness). New Playwright expected (the interaction model + inline-edit round-trips).
- **Playwright suite** — baseline preserved; this session adds specs (selection/handle/Edit-affordance interaction; inline-edit save round-trip; the builder↔public parity harness). `feedback_test_runs_not_parallel` applies.
- **Build/version discipline (carry-forward — load-bearing):** bump repo-root `VERSION` to `0.NNN.<iteration>` each iteration; the deploy pipeline hard-fails a forgotten/duplicate bump. `public/css/admin.css` is hand-edited and served directly — **no build step** for the `.inline-editable` CSS; Vite-compiled Vue/SCSS changes need `npm run build` (see the base-prompt front-end build decision rule).
- **Planning state:** if scheduled as a release-plan entry / outline stub, that entry is canonical and this prompt is the delta. Otherwise emergent — creates no plan structure beyond the close-gate bookkeeping.
- **Housekeeping inbox** at `sessions/housekeeping-inbox.md` — this is a focused critical-path feature, not a housekeeping batch; do not absorb inbox items.

---

## Process rules for every session

Base-prompt process rules apply in full. Session-specific notes:

- **This has a real design problem; the user is in the loop for it.** The inline formatting affordance (Phase 3) is a "*is this right?*" surface, not a "*does this work?*" surface — per the verify-objective / pull-user-for-judgment rule, build to a working candidate, then **pause and put it in front of the user**. Do not silently pick a formatting UX and build deep. The Quill-vs-alternative call (see Open Questions) is surfaced *before* Phase 3 goes deep.
- **The token/data-driven gating is mandatory safety, not optional polish.** Inline editing writes the **raw stored config string only**, never serialized rendered DOM. It is gated **off** for any instance containing `{{tokens}}` and for data-driven/templated widgets (EventsListing rows, listing/pager bodies). The gate is a per-widget-definition capability declaration; confirm its shape at start.
- **Suppress the preview-refresh echo on the actively-edited node.** A config save triggers a full server re-render that swaps the `v-html` blob and would destroy a live editor mid-keystroke. Mirror the `RichTextField.vue` dirty-guard. This is required for Phase 2 to function at all.
- **Do not repeat the 137 toolbar failure.** The documented failure mode is a floating toolbar acting across *multiple simultaneous* editable regions. Whatever Phase 3 ships must bind to the **single active editor only** (contextual/selection-anchored), and formatting must not regress into the right-hand Inspector.
- **Keep canvas interactions minimal — no scroll-jacking.** This project has a documented aversion to fighting the preview canvas (session 269 abandoned a scroll-clamp for exactly this reason). The handle/Edit/contenteditable affordances must not introduce scroll capture or focus-scroll fights.
- **Playwright is the verification mechanism.** The selection model, handle reorder, inline-edit save round-trip, and the builder↔public parity are all objectively verifiable in a browser — write specs and close those loops yourself; pull the user in only for the formatting-UX judgment.
- **Run the fast suite + the new Playwright specs (sequentially) when implementation is complete.** Green, every count delta explained.
- **Pause for manual UX testing at the Phase 3 handoff and at session end.** Announce what is ready, surface the formatting-affordance candidate (and the Quill decision as taken) for the user's visual judgment, then stop and wait. Do not suggest closing.

---

## ── SESSION CLOSE GATE ──────────────────────────────────────────────────────

**Everything below happens only when the user explicitly says to close.** Do not ask, suggest, or pipeline. If the session split per Rule 11, the close gate applies to whatever phases shipped; the remainder carries forward into the next iteration/session prompt.

### Phase 1 — Attenuate and prepare next session

After implementation and manual UX testing are complete, draft the next session's documents only when the user names the next session (likely the next phase-slice of this work if it split). Do not auto-pipeline.

### Phase 2 — Close

When the user says to close:

- **Session log:** `sessions/NNN. Page Builder — In-Page Text Editing, Handles & Inline Formatting — Log.md` per `template-session-log.md`. Document: the selection/affordance model change; which widgets were inline-enabled and the capability-gate shape; the token/data-driven exclusions; the preview-refresh-suppression mechanism; the formatting-affordance solution and the Quill-vs-alternative decision **with rationale**; the builder↔public parity harness and what it asserts; new Pest/Playwright; `VERSION` bump; any drift; what (if anything) carried forward to a successor session.
- **Update `sessions/completed-sessions.md`**, archive the previous session's files, commit on the final `session-NNN/N` branch, notify the user, do not push. Never push to main / force-push / merge to main.
- If scheduled as a plan entry / track: update `release-plan.md` / `session-outlines.md` / the track doc per the standard close pattern.
- **Do not begin the next session** until the user explicitly starts it.

---

## Style rules

Base-prompt style rules apply. Session-specific:

- **Iteration commit messages describe the deliverable.**
- **PM-level tone for status reports** per `memory/feedback_pm_level_tone.md`.
- **No docstrings/comments/type annotations on code you did not write.**
- **Extend, don't reinvent.** Inline editing re-activates the dormant `data-config-key` hooks and the `.inline-editable` CSS that already exist, routes through the existing `updateLocalConfig`→debounced-save path, and reuses the single `WidgetRenderer`. The handle work repoints an existing `vuedraggable` `handle=` selector. This is a re-activation + UX-solve + parity-guard, not a page-builder rewrite. Do not introduce a new editor framework unless the Quill-vs-alternative decision explicitly lands there with the user's sign-off.

# Base Prompt — Session NNN (Page Builder Inline Editing — Session B: Formatting Toolbar & Parity) · DRAFT FOR REVIEW

> **Draft.** Number deliberately unassigned (placeholder `NNN-pbeditB`). **Critical-path, Beta-1-blocking.** This is **Session B of a two-session split**. **Session A (`sessions/NNN-pbeditA. *`) is a hard prerequisite** — B cannot run until A has shipped (interaction model + in-page text editing + the simplified repeater control live). When scheduled, likely warrants a `release-plan.md` §entry + `session-outlines.md` stub; flag at scheduling, do not fabricate plan structure here. May split per Rule 11.

**Start now.** Execute the reading list below, open the session prompt at `sessions/NNN-pbeditB. Page Builder — Inline Formatting Toolbar & Builder↔Public Parity.md`, then begin the work. No confirmation needed.

---

We are about to begin a new session: **NNN. Page Builder — Inline Formatting Toolbar & Builder↔Public Parity**.

This session has **no execution-order position in `sessions/release-plan.md` yet** — emergent, critical-path. It is **Session B**, building strictly on Session A. Its centerpiece is the hard UX problem deferred from A: formatting controls that live *with* the inline-edited text (not in the right Inspector), plus a standing builder↔public appearance-parity guard and widening the eligibility gate.

This session is **NOT boundary-touching** — no Fleet Manager surface. **CRM contract stays at v2.3.0.** **No DB schema change.** If the work appears to need a Fleet/`/api/health`/schema touch, stop and surface — scope drifted.

**Session shape:** feature-style, UX-heavy, with one genuinely hard UX surface and one standing-infrastructure deliverable. One-sentence summary: build a **single shared custom Vue formatting toolbar driven by the Quill API** (settled decision), bound to the active editor under three named constraints; **remove rich-text formatting from the Inspector** (ending A's interim); add **regular automated builder-preview ↔ public-render appearance lock-step tests**; and **widen the inline-eligibility gate** to the remaining safe widgets.

Before doing anything else:

1. Re-read the session templates only if uncertain about a structural detail — stable since 276.
2. Read `sessions/session-outlines.md` — Active-tracks + Housekeeping Inbox. No forward stub unless added at scheduling.
3. No `release-plan.md` entry unless added at scheduling. Read Phase E for Beta-1 context only.
4. Not part of an active track unless created at scheduling.
5. **Read Session A's log** (`sessions/NNN. Page Builder — Inline Editing Foundation — Log.md`) first — it is the authoritative record of what B inherits (which widgets are inline-enabled, the code-declared gate shape, the Quill instance lifecycle/refresh-suppression mechanism, the repeater control, the Tier-B guard, and exactly how the formatting interim was left). Adapt to what A actually shipped, not what A's prompt planned.
6. Read `docs/app-reference.md` — the **single render path** (`WidgetRenderer::render()` feeds both public and the builder preview). This is the asset that makes the Phase 4 parity tests tractable. Read `docs/schema/README.md`; no schema this session.
7. Read the leverage surfaces before touching them:
   - `resources/js/page-builder-vue/components/PreviewRegion.vue` + the inline-edit layer A built (the active-editor binding, lifecycle, refresh-suppression) — the toolbar binds to this.
   - `resources/js/page-builder-vue/components/InspectorPanel.vue` + `components/fields/RichTextField.vue` — the existing Quill integration; B **removes formatting from here** and stands up the custom toolbar instead. `RichTextField.vue` is the API-usage reference for `quill.format()`/`getFormat()`/`editor-change`.
   - Whatever component A created for the active-editor/store wiring (per A's log) — the shared toolbar re-targets the active instance through it.
   - `app/Services/...WidgetRenderer` + the public layout + the page-builder preview path — both consume the single render path; Phase 4 asserts they are appearance-equivalent.
   - The font registration path (`Quill.import('formats/font')` + attributor) and the font CSS load — for the C3 font picker.
8. Read `sessions/NNN-pbeditA. Page Builder — Inline Editing Foundation.md` for the canonical PricingChart stress test, the 40-widget Tier-B exempt set + guard, and the capability-gate decision — Phase 5 (widen the gate) re-runs those guards; do not re-derive them.
9. Read the history note: the one design objection on record was a floating toolbar over **multiple simultaneous** editable regions (137). B's toolbar must make that structurally impossible (one shared toolbar, single active editor). Inline editing itself was never removed for cause.
10. Read `sessions/NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard.md` (or its scheduled-number equivalent) — Phase 4's parity tests are **complementary, not duplicative**: that guard = *served bundle ≡ saved settings*; Phase 4 = *builder preview render ≡ public render appearance*. If that session shipped, extend its harness; if not, keep the seams distinct and cross-reference.
11. Note drift in a brief work-log entry; proceed unless something needs a decision per the drift/decision-threshold rules.

---

## Starting state inherited from Session A

*(Required. Re-confirm against Session A's actual log at start; adapt to drift silently. The bullets below assume A closed normally.)*

- **CRM contract at v2.3.0** — unchanged; B does not touch it. Not boundary-touching.
- **Schema baseline:** A added none; B adds none. The capability gate is declared in widget code (A's settled decision) — B extends that declaration to more widgets in Phase 5, still no migration.
- **Interaction model live (A):** top-left hover-in drag handle, body-click/drag selection, explicit top-right Edit affordance → Inspector. Whole-panel selection is gone.
- **In-page text editing live (A):** on TextBlock, Hero, ThreeBuckets bodies; PricingChart proven (path-addressed multi-node binding incl. nested `columns[i].attribute_rows[j]`, the simplified structural repeater control, buttons split). Refresh-suppression is text-only; the Quill instance lifecycle (mount/dispose on (de)select + preview re-render) is solved — **B's toolbar binds to that existing active-editor mechanism; do not rebuild it.**
- **Formatting interim (A → B):** during A, rich-text *formatting* still used the existing Inspector `RichTextField`. **B's job is to replace that with the contextual toolbar and remove formatting from the Inspector.** Confirm from A's log exactly how the interim was wired before ripping it out.
- **Discoverability live (A):** selection-scoped progressive disclosure; unselected preview pixel-clean; schema-`label` ghost text on empty slots; `.inline-editable` hover/focus active. B preserves this; the toolbar's appearance on focus becomes the editing-confirmation signal.
- **Tier-B guard live (A):** the regression test that no Tier-B exempt field is annotated `data-config-key`. Phase 5 widening must keep it green.
- **Fast suite green at A's close baseline** — confirm exact count from A's log. New Pest expected (parity harness; widened-gate eligibility). New Playwright expected (toolbar behaviour incl. selection preservation; parity harness; widened widgets).
- **Playwright suite** — A's specs preserved; B adds. `feedback_test_runs_not_parallel` applies.
- **Build/version discipline (load-bearing):** bump `VERSION` to `0.NNN.<iteration>` each iteration. Vue/SCSS → `npm run build`; `public/css/admin.css` hand-edited/served directly.
- **Housekeeping inbox** — focused critical-path feature; do not absorb inbox items.

---

## Process rules for every session

Base-prompt process rules apply in full. Session-specific notes:

- **The editor is settled; only the toolbar's look/feel is a user-judgment surface.** Quill stays; custom Vue toolbar over its API, built-in toolbar module disabled (session prompt "Decided design"). Do not relitigate or evaluate alternatives; if an unforeseen blocker appears, stop and surface rather than swapping. The toolbar *engineering* is objective — verify it yourself; its *look and feel* is "*is this right?*" — build the candidate, then pause for the user before widening.
- **The three constraints are non-optional, engineered from line one (not retrofitted):** **C1** selection/focus preservation — a toolbar click must not collapse the editor selection (`@mousedown.prevent` or capture/restore); **C2** one shared, app-level toolbar that re-targets the active Quill instance (never one-per-region — makes the 137 multi-region failure structurally impossible); **C3** font picker via `Quill.import('formats/font')` + attributor + font CSS, link editing via a small Vue popover (not Quill's raw `prompt()`).
- **Removing formatting from the Inspector is in scope and load-bearing** — A left it there as an interim; B must actually remove it so the experience is coherent (formatting with the text, non-text style in the Inspector). Confirm nothing else depends on the Inspector formatting path before removing.
- **Phase 4 parity is a standing deliverable, not a check-and-discard.** It leverages the single `WidgetRenderer` path to assert builder-preview ≡ public-render appearance for a representative widget set; coordinate (don't duplicate) the stale-stylesheet drift guard.
- **Phase 5 widening re-runs A's guards.** Each newly-eligible widget must keep the Tier-B guard green and pass the Phase 2 (A) round-trip + Phase 4 parity. No Tier-B field is ever annotated.
- **Playwright is the verification mechanism** for toolbar behaviour (each format applies, selection survives the click, state reflects on `editor-change`), the parity harness, and each widened widget — close those loops yourself.
- **Run the fast suite + new Playwright (sequentially) when complete.** Green, every count delta explained.
- **Pause for manual UX testing at the Phase 3 handoff and at session end** (toolbar look/feel; the now-coherent inline experience). Announce, then stop and wait. Do not suggest closing.

---

## ── SESSION CLOSE GATE ──────────────────────────────────────────────────────

**Everything below happens only when the user explicitly says to close.** Do not ask, suggest, or pipeline.

### Phase 1 — Attenuate and prepare next session

After implementation + manual UX testing, draft a successor only if the user names one — B is the terminal session of this split. If Phase 5 did not finish widening every safe widget, the remainder is a carry-forward note, not an auto-drafted session.

### Phase 2 — Close

When the user says to close: write `sessions/NNN. Page Builder — Inline Formatting Toolbar & Builder↔Public Parity — Log.md` per `template-session-log.md` (the custom toolbar + how C1/C2/C3 were satisfied; the Inspector-formatting removal; the parity harness + what it asserts + its coordination with the drift guard; the widened widget set; new Pest/Playwright; `VERSION` bump; drift; any widening carry-forward). Update `completed-sessions.md`, archive the previous session's files, commit on the final `session-NNN/N` branch, notify, do not push. Never push to main / force-push / merge to main. If scheduled as a plan entry/track, update those per the standard pattern. Do not begin any successor until the user starts it.

---

## Style rules

Base-prompt style rules apply. Session-specific: iteration commit messages describe the deliverable; PM-level status tone per `memory/feedback_pm_level_tone.md`; no docstrings/comments/type-annotations on code you did not write. **Extend, don't reinvent** — the toolbar drives the **existing** Quill (built-in toolbar off) via its API and binds to **A's** active-editor mechanism; the parity tests leverage the **existing** single `WidgetRenderer`; Phase 5 extends the **existing** code-declared gate. No new editor framework, no second render path, no re-architecting A's lifecycle. This is a UX-solve + parity-guard + gate-widening session on top of A, not a rebuild.

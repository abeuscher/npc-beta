# NNN. Page Builder — Inline Formatting Toolbar & Builder↔Public Parity · DRAFT FOR REVIEW

> **Session B of a two-session split.** Critical-path, Beta-1-blocking. **Session A (`sessions/NNN-pbeditA. *`) is a hard prerequisite** and must have shipped: the interaction model, in-page text editing on the safe set, the Quill instance lifecycle/refresh-suppression, the simplified repeater control, and the Tier-B guard are all live. B delivers the hard UX surface deferred from A plus a standing parity guard.

One sentence: replace A's interim (formatting in the Inspector) with a single shared **custom Vue formatting toolbar driven by the Quill API**, bound to the active editor under three named constraints; add **regular builder↔public appearance lock-step tests**; and **widen the inline-eligibility gate** to the remaining safe widgets.

---

## Stub reference (emergent, critical-path; continues Session A)

No `release-plan.md` entry / `session-outlines.md` stub yet — emergent. B is the second half of the page-builder inline-editing work. Read **Session A's log** first; it is canonical for what B inherits. The research grounding (architecture + history + 40-widget pass) is canonical in Session A's prompt — do not re-derive; B references it for Phase 5.

**Load-bearing facts B depends on (confirm against A's log):** the builder preview is server-rendered `v-html`; one render path (`WidgetRenderer::render()`) for public + preview; A's inline-edit layer owns the active-editor binding, the Quill instance lifecycle (mount/dispose on (de)select + preview re-render), and text-only refresh-suppression; formatting was left in the Inspector `RichTextField` as a deliberate interim for B to replace.

---

## Decided design — rich-text editor & toolbar (settled; do not relitigate in-session)

**Quill is retained. The formatting controls are a fully custom Vue toolbar driven by Quill's public API** (`quill.format()` / `quill.getFormat()` / the `editor-change` event), with Quill's built-in toolbar module disabled. This is a first-class, documented Quill pattern, not a workaround; the engine, the Delta/HTML it emits, and therefore the existing `PageWidget::saving` sanitization boundary are **unchanged** — no migration, no contract change, no storage-format change. A swap to another editor is **off the table** unless an unforeseen blocker surfaces, in which case stop and surface to the user (do not pick an alternative in-session).

Three intrinsic risks are **named design constraints**, engineered from line one, not discovered:

- **C1 — Selection/focus preservation (the primary footgun).** Clicking a toolbar control must not collapse the editor selection. Use `@mousedown.prevent` on the controls (or explicit capture-and-restore of the range). Non-optional; most likely the cause of first-pass pain.
- **C2 — One shared, app-level toolbar bound to the active editor.** Not one toolbar per editor. A single toolbar component re-targets whichever Quill instance is focused (binding through A's existing active-editor mechanism — do not rebuild it). This makes the 137 multi-region conflict structurally impossible.
- **C3 — Font picker = registration, not difficulty.** Register the font whitelist via `Quill.import('formats/font')` + an attributor and ensure the font CSS is loaded; link editing gets a small Vue popover, not Quill's raw `prompt()`. Bounded plumbing.

---

## Open questions to resolve at session start

1. **Parity-test relationship to the stale-stylesheet drift guard.** Coordinate with `sessions/NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard.md`: its guard = *served bundle ≡ saved settings*; this Phase 4 = *builder preview render ≡ public render appearance*. Complementary. If that session shipped first, **extend its harness** rather than standing up a parallel one; if not, keep the seams distinct and cross-reference. Decide which at start based on whether the drift-guard session has landed.

*(The editor/Quill decision, the three constraints, the capability-gate location, and the PricingChart/Tier-B canon are all settled — see above and Session A's prompt. Nothing else is open.)*

---

## Phases

### Phase 3 — The inline formatting toolbar (custom Vue over Quill API; user-in-the-loop on look/feel)

Per the Decided design: build the **one shared, app-level Vue toolbar** that drives the active Quill instance via its API (built-in toolbar module disabled) — bold / italic / link / lists / headings / font as the content model supports. Constraints **C1 / C2 / C3** implemented from the start. Bind to A's existing active-editor mechanism; do not rebuild lifecycle/refresh-suppression. **Remove rich-text formatting from the Inspector** (end A's interim) so the experience is coherent: text formatting lives with the text, the Inspector keeps only non-text style (appearance / layout / colour). Confirm nothing else depends on the Inspector formatting path before removing it. The *engineering* is objective — verify it yourself (Playwright: each format applies, selection survives the toolbar click, state reflects on `editor-change`, no regression to multi-region behaviour). The *look and feel* is a user-judgment surface: build the working candidate, then **pause and put it in front of the user** before widening (Phase 5).

### Phase 4 — Builder ↔ public appearance lock-step parity tests (standing guard)

Add regular automated tests asserting the builder preview render and the public render are appearance-equivalent for a representative widget set, leveraging the single `WidgetRenderer` path. Assert structural + composed-style equivalence (the render-layer complement to the stale-stylesheet bundle-layer guard). Coordinate per Open Q1 (extend the drift-guard harness if it exists; else distinct + cross-referenced). A standing deliverable, not a check-and-discard.

### Phase 5 — Widen the inline-eligibility gate

With the toolbar and parity guard in place, extend inline eligibility (the code-declared, per-node, annotation-opt-in gate from A) to the remaining safe annotated widgets — e.g. DonationForm heading, EventsListing **static** heading (*not* its data rows), and any other Tier-A-clear display-prose nodes. Each addition: annotate only genuine display-prose nodes; **keep the Tier-B guard green** (no Tier-B field ever annotated); re-run the Session-A inline round-trip and the Phase 4 parity for that widget. Unfinished widening at close is a carry-forward note, not an auto-drafted successor.

---

## Out of scope

- Anything Session A already delivered (interaction model, the inline-edit layer, lifecycle/refresh-suppression, the simplified repeater control, the Tier-B guard) — B builds on it, does not redo or re-architect it.
- Inline editing of data-driven/templated/token-bearing content, and the Tier-B exempt fields — gated off by design, permanently (Phase 5 must not breach this).
- Any Fleet / `/api/health` / response-schema / DB-schema change. CRM contract stays v2.3.0. Surface immediately if the work appears to need any.
- A new editor framework, a second render path, or re-architecting A's active-editor/lifecycle mechanism.
- Auto-drafting a successor — B is terminal for this split.

---

## Testing

- **Slow groups:** none expected.
- **New Pest:** widened-gate eligibility for the newly-annotated widgets; the Tier-B guard stays green across widening; parity-harness support assertions as needed.
- **New Playwright:** Phase 3 — each format applies with selection surviving the toolbar click, state reflects on `editor-change`, single-active-editor behaviour (no multi-region toolbar), Inspector no longer exposes rich-text formatting; Phase 4 — the builder↔public parity harness (standing); Phase 5 — each widened widget round-trips inline + passes parity.
- **Manual (user judgment):** the Phase 3 toolbar **look and feel** and the now-coherent inline experience — paused for at the Phase 3 handoff and at session end. The editor choice and engineering are settled, not a user decision. Pest and Playwright run sequentially, never in parallel.

---

## Closing steps

Follow the close gate in the base prompt. Session-specific: log `sessions/NNN. Page Builder — Inline Formatting Toolbar & Builder↔Public Parity — Log.md`; artifact = the custom Vue toolbar (C1/C2/C3 satisfied) + Inspector formatting removed + the standing builder↔public parity harness + the widened eligible widget set + new Pest/Playwright + `VERSION` `0.NNN.x` + any widening carry-forward; branch `session-NNN/N`; B is terminal for this split — draft a successor only if the user names one.

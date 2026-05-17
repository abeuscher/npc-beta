# NNN. Page Builder — In-Page Text Editing, Handle-Based Reorder & Inline Formatting Affordance · DRAFT FOR REVIEW

> **Draft for the user to review.** Critical-path, Beta-1-blocking. The current "select the whole panel → edit everything in the right Inspector" model does not work well enough for beta testers. This restores in-page text editing, replaces whole-panel selection with explicit handle + Edit affordances, solves the inline rich-text formatting UX as a first-class problem, and adds standing tests that keep the builder preview and the public site in appearance lock-step.

One sentence: make the page builder editable *on the page* — drag via a real handle, trigger the Inspector via an explicit Edit affordance, edit text in place with a properly-designed inline formatting toolbar, all gated to the widgets where it's safe, and keep the preview and public render provably in lock-step.

---

## Stub reference (emergent, critical-path forcing function)

No `release-plan.md` entry or `session-outlines.md` stub yet — emergent. Forcing function: **beta testers cannot author pages effectively with the present model.** The whole widget panel is one selectable target and all editing (including text) happens in the right-hand Inspector; this is too indirect to ship. This session is grounded in two completed research passes (architecture + feature history) whose load-bearing findings are folded in below — do not re-derive them, but confirm specifics against the live code at start and adapt to drift.

**Load-bearing facts (canonical for this session's design):**

- The builder preview is **server-rendered HTML injected via `v-html`** behind a transparent click-to-select overlay (`PreviewRegion.vue`; `.preview-region__html { pointer-events: none }`). There is **one render path** — `WidgetRenderer::render()` — for both public and preview. There is no client-side model of the text; the text exists only inside an opaque server HTML string.
- Inline editing **existed** (session 137, Livewire) and was **never removed for cause** — it was de-scoped for sequencing (139) then dropped by attrition in the 147–152 Vue rewrite. The **only** design objection on record: a floating rich-text **toolbar over multiple editable regions** caused UX conflict — plain contenteditable shipped fine. That single constraint still applies and shapes Phase 3.
- Dormant scaffolds the rewrite never cleaned up, ready to re-activate: `data-config-key`/`data-config-type` annotations on **6 widget templates** (TextBlock, Hero, ThreeBuckets ×2, EventsListing, DonationForm), and the `.inline-editable` CSS at `public/css/admin.css:677–692`.
- Persistence/security seams already hold: the config-save allow-list includes `content`; `PageWidget::saving` sanitizes richtext. No schema, no new API — inline editing reuses `updateLocalConfig()` → debounced save.
- Two hazards are intrinsic and must be designed around, not discovered: **(a) token substitution** runs before render — writing serialized DOM back to config bakes resolved `{{tokens}}` and destroys templates; **(b) the preview-refresh echo** — every save re-renders and swaps the `v-html` blob, destroying a live editor mid-keystroke.

---

## Canonical UX requirements (from the product owner — these are not negotiable shape, only mechanism is open)

1. **Drag handle, top-left, hover-in.** A real handle element (not the whole surface) anchored top-left of each widget panel, hidden by default, **fading/sliding into view on hover**, carrying a clear drag icon so the user knows it's draggable. Reorder behaviour/persistence is unchanged — only the grab target changes.
2. **Selection model replaced.** Whole-panel selection is removed. Selecting a panel happens by **dragging it, or clicking in its body area**. Separately, there must be an **explicit "Edit" affordance** — a top-right per-panel control that reveals on hover (label/icon, e.g. says "Edit") — that the user unmistakably associates with **opening the Inspector**. The user must never be guessing what triggers the style panel.
3. **The rich-text toolbar problem must be solved well.** Formatting controls **must exist** — but they **cannot live in the right-hand Inspector** once text is edited inline (incoherent as an experience). This piece is split out and solved as a first-class UX problem: an inline/contextual formatting affordance bound to the actively-edited text. **Quill replacement is explicitly on the table** — owner's inclination is Quill remains the right tool, but if a swap is ever justified, this is the session to decide it (see Open Questions).
4. **Builder ↔ public appearance lock-step, tested regularly.** Because builder-preview and public render are *not* presently guaranteed to match appearance-wise, this session adds automated tests that assert they stay in lock-step — a standing regression guard, not a one-off check.

---

## Open questions to resolve at session start (the real decisions — surface, don't silently pick)

1. **Quill vs. a replacement editor — the big one.** Default recommendation: **keep Quill**, delivered as a *contextual/selection-anchored* formatting affordance (e.g. Quill bubble-theme or a thin custom toolbar bound to the single active Quill instance), not the Inspector field. Evaluate a swap *only* against concrete criteria: (a) can Quill bind cleanly to a contenteditable node mounted into server-rendered HTML without fighting the `v-html` lifecycle; (b) can a single-active-editor contextual toolbar be built without the 137 multi-region conflict; (c) sanitization parity with the existing `PageWidget` boundary. If Quill clears these (expected), keep it and **do not open a migration**. If it genuinely cannot, present the alternative + cost to the user before building — this is a user-sign-off decision, not an in-session pick.
2. **Capability-gate shape.** Per-widget-definition declaration (PHP property/method on the widget class — no migration; recommended) vs. a `widget_types` column (schema change — must be surfaced). Define what "inline-eligible" means precisely: a single editable text/richtext config key, no `{{tokens}}` in the instance, not data-driven/templated.
3. **Scope split (Rule 11).** Recommended split if it inflates: **Session A = Phases 1–2** (interaction model + first safe inline slice + preview-refresh suppression), **Session B = Phases 3–4** (formatting affordance + lock-step parity tests + widen the gate). Confirm the split with the user rather than running it as one mega-session.
4. **Parity-test relationship to the stale-stylesheet drift guard.** Coordinate with `sessions/NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard.md`: its guard = *served bundle ≡ saved settings*; this Phase 4 = *builder preview render ≡ public render appearance*. Complementary. If the drift-guard session shipped first, extend its harness; if not, keep the seams distinct and cross-reference.

---

## Phases

### Phase 1 — Interaction-model rework (independently shippable)

Replace whole-panel selection. Add: (a) the top-left hover-in **drag handle** with a drag icon, repointing the existing `vuedraggable` `handle=` selector at it (reorder payload/persistence untouched); (b) body-click / drag selects the panel; (c) the explicit top-right hover-in **"Edit" affordance** that opens the Inspector for that widget. No inline text editing yet. Playwright: selection via body-click, reorder via the handle, Inspector opens via the Edit affordance, and the *old* whole-panel-select behaviour is gone.

### Phase 2 — Inline text editing on safe widgets, behind the capability gate

Activate a Vue contenteditable layer in `PreviewRegion.vue` on `[data-config-key]` nodes **of the selected, inline-eligible widget only**. Bind edits to the **raw config string**, route through `updateLocalConfig()` → existing debounced save, on blur + debounce. Implement the **capability gate** (Open Q2) and the **token/data-driven exclusions**. Implement **preview-refresh suppression** for the actively-edited node (mirror the `RichTextField.vue` dirty-guard) so a server re-render can't clobber a live editor. Confirm sanitization + allow-list cover every inline key (`content`, `heading_N`, `body_N`). Start with the unambiguous targets: **TextBlock**, **Hero** (token-free instances), **ThreeBuckets** bodies. Playwright: type-in-place → blur → reload → persisted; an instance with a `{{token}}` is **not** inline-editable; a data-driven widget is **not** inline-editable.

### Phase 3 — The inline formatting affordance (the hard UX problem; user-in-the-loop)

Solve formatting controls *with the text*, not in the Inspector. Build a **single-active-editor, contextual** formatting UI (Quill bubble-theme or thin custom toolbar per Open Q1) — bold/italic/link/lists/headings as the content model supports. Hard constraint: it binds to **one** active editor at a time (the 137 multi-region floating-toolbar failure must not recur). The Inspector retains only **non-text** style (appearance/layout/colour). Build a working candidate, then **pause and put it in front of the user** for the visual/UX judgment and the Quill decision sign-off before going deep or widening.

### Phase 4 — Builder ↔ public appearance lock-step parity tests (standing guard)

Add regular automated tests asserting the builder preview render and the public render are appearance-equivalent for a representative widget set — leveraging the single `WidgetRenderer` path. Assert structural + composed-style equivalence (the same seam the stale-stylesheet incident exploited at the bundle layer; this guards the render layer). Coordinate with the drift-guard session (Open Q4). This is a deliverable, not a check-and-discard.

### Phase 5 — Widen the gate *(optional / may defer to a successor)*

Once Phases 2–3 hold, extend inline eligibility to the remaining safe annotated widgets (DonationForm heading, EventsListing static heading — *not* its data rows). Each addition re-runs the Phase 2 + Phase 4 guards. Defer to a successor session if scope split per Open Q3.

---

## Out of scope

- Inline editing of **data-driven / templated** content (EventsListing rows, listing/pager bodies, anything token-bearing) — gated off by design, permanently in this session's scope.
- Any Fleet Manager contract / `/api/health` / schema change. CRM contract stays v2.3.0. Surface immediately if the work appears to need any of these.
- A page-builder architecture rewrite, a new state model, or replacing `v-html` server-rendered preview with client-rendered widgets — explicitly not this; the design works *with* the single render path.
- Re-introducing a floating toolbar that spans multiple simultaneous editable regions (the documented 137 failure).
- Mobile/breakpoint editing affordances — authoring is a desktop-builder surface; responsive *output* is unchanged and already handled elsewhere (E5/E7). Note, don't build.
- The stale-stylesheet *bundle* drift guard itself — that is its own session; Phase 4 here is the *render-parity* complement, coordinated, not duplicated.

---

## Testing

- **Slow groups:** none expected.
- **New Pest:** capability-gate eligibility logic; token/data-driven exclusion; sanitization + allow-list coverage of every inline key.
- **New Playwright:** Phase 1 interaction model (handle reorder, body-click select, Edit-affordance opens Inspector, old whole-panel select gone); Phase 2 inline-edit save round-trip + the two negative gates; Phase 4 the builder↔public parity harness (standing).
- **Manual (user judgment):** the Phase 3 formatting affordance look/feel and the Quill decision — paused for at the Phase 3 handoff. Pest and Playwright run sequentially, never in parallel.

---

## Closing steps

Follow the close gate in the base prompt. Session-specific:

- **Log file:** `sessions/NNN. Page Builder — In-Page Text Editing, Handles & Inline Formatting — Log.md`.
- **Artifact:** the new interaction model; inline editing live on the safe widget set behind the capability gate; the contextual formatting affordance + the recorded Quill-vs-alternative decision; the builder↔public parity harness; new Pest/Playwright; `VERSION` `0.NNN.x`; any carry-forward to a successor session if split.
- **Branch:** `session-NNN/N` (final iteration), once numbered.
- **Next session:** the deferred phase-slice if split per Open Q3, drafted only when the user names it.

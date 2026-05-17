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
5. **Column / repeater management is simplified, not removed.** For widgets with an arbitrary number of repeated units (the canonical example: **PricingChart** columns; also ThreeBuckets-style sets), once text is edited *inline* the Inspector repeater no longer needs to manage text — it collapses to **structural** management only: a **column-specific table of the repeated units stacked as selectable rows, each with an order control and a delete/trash icon, plus an add ("+") button at the bottom**. This is a deliberate simplification of the existing `RepeaterField.vue` / `RepeaterRowField.vue` surface — the text moved inline; what remains is add / remove / reorder of the units. It is a *structural* affordance and stays in the Inspector (it is not text, so it does not belong in the inline formatting toolbar).

---

## Decided design — rich-text editor & toolbar (settled; do not relitigate in-session)

**Quill is retained. The formatting controls are a fully custom Vue toolbar driven by Quill's public API** (`quill.format()` / `quill.getFormat()` / the `editor-change` event), with Quill's built-in toolbar module disabled. This is a first-class, documented Quill pattern, not a workaround; the engine, the Delta/HTML it emits, and therefore the existing `PageWidget::saving` sanitization boundary are **unchanged** — no migration, no contract change, no storage-format change. A swap to another editor is **off the table** unless an unforeseen blocker surfaces, in which case stop and surface to the user (do not pick an alternative in-session).

Three intrinsic risks are **named design constraints for Phase 3**, to be engineered from line one, not discovered:

- **C1 — Selection/focus preservation (the primary footgun).** Clicking a toolbar control must not collapse the editor selection. Use `@mousedown.prevent` on the toolbar controls (or explicit capture-and-restore of the range). This is non-optional and is most likely what caused first-pass pain.
- **C2 — One shared, app-level toolbar bound to the active editor.** Not one toolbar per editor. A single toolbar component re-targets whichever Quill instance is focused. This makes the 137 multi-region conflict structurally impossible and is the clean answer to the multiple-editors concern. The genuine work here is **instance lifecycle**, not binding: Quill instances must mount/dispose cleanly as widgets are (de)selected and as the `v-html` preview re-renders — this is the *same* problem as Phase 2's preview-refresh suppression and must be solved together (dispose listeners, no leaks, preserve/restore selection across a refresh).
- **C3 — Font picker = registration, not difficulty.** Register the font whitelist via `Quill.import('formats/font')` + an attributor and ensure the corresponding font CSS is loaded; link editing gets a small Vue popover rather than Quill's raw `prompt()`. Bounded plumbing, explicitly not a risk item.

---

## Open questions to resolve at session start (the genuinely-open decisions)

1. **Capability-gate shape.** Per-widget-definition declaration (PHP property/method on the widget class — no migration; recommended) vs. a `widget_types` column (schema change — must be surfaced). Define "inline-eligible" precisely: a single editable text/richtext config key, no `{{tokens}}` in the instance, not data-driven/templated.
2. **Scope split (Rule 11).** Recommended split if it inflates: **Session A = Phases 1–2 (+2b)** (interaction model + first safe inline slice + preview-refresh/lifecycle + the simplified column/repeater control), **Session B = Phases 3–4** (custom toolbar + lock-step parity tests + widen the gate). Confirm the split with the user rather than running one mega-session.
3. **Parity-test relationship to the stale-stylesheet drift guard.** Coordinate with `sessions/NNN. Post-Incident Test Integrity — Stale-Stylesheet Drift Guard.md`: its guard = *served bundle ≡ saved settings*; this Phase 4 = *builder preview render ≡ public render appearance*. Complementary. If the drift-guard session shipped first, extend its harness; if not, keep the seams distinct and cross-reference.

---

## Phases

### Phase 1 — Interaction-model rework (independently shippable)

Replace whole-panel selection. Add: (a) the top-left hover-in **drag handle** with a drag icon, repointing the existing `vuedraggable` `handle=` selector at it (reorder payload/persistence untouched); (b) body-click / drag selects the panel; (c) the explicit top-right hover-in **"Edit" affordance** that opens the Inspector for that widget. No inline text editing yet. Playwright: selection via body-click, reorder via the handle, Inspector opens via the Edit affordance, and the *old* whole-panel-select behaviour is gone.

### Phase 2 — Inline text editing on safe widgets, behind the capability gate

Activate a Vue contenteditable layer in `PreviewRegion.vue` on `[data-config-key]` nodes **of the selected, inline-eligible widget only**. Bind edits to the **raw config string**, route through `updateLocalConfig()` → existing debounced save, on blur + debounce. Implement the **capability gate** (Open Q2) and the **token/data-driven exclusions**. Implement **preview-refresh suppression** for the actively-edited node (mirror the `RichTextField.vue` dirty-guard) so a server re-render can't clobber a live editor. Confirm sanitization + allow-list cover every inline key (`content`, `heading_N`, `body_N`). Start with the unambiguous targets: **TextBlock**, **Hero** (token-free instances), **ThreeBuckets** bodies. Playwright: type-in-place → blur → reload → persisted; an instance with a `{{token}}` is **not** inline-editable; a data-driven widget is **not** inline-editable.

### Phase 2b — Simplified column / repeater management (structural; Inspector-side)

For widgets with arbitrary repeated units (canonical: **PricingChart** columns — `app/Widgets/PricingChart/PricingChartDefinition.php`; the shape generalizes to other repeaters), collapse the existing `RepeaterField.vue` / `RepeaterRowField.vue` surface to **structure only**: a column-specific table of the units stacked as selectable rows, each with an order control + a delete/trash icon, plus a bottom "+" add button. Text content of each unit is now edited inline (Phase 2), so the repeater stops carrying text fields and becomes add / remove / reorder. Persistence is the existing repeater config save path — unchanged shape, simplified UI. Playwright: add a column → reorder → delete → persists; inline-editing a column's text round-trips independently of the structural control.

### Phase 3 — The inline formatting toolbar (custom Vue over Quill API; user-in-the-loop on look/feel)

Per the **Decided design** section: build the **one shared, app-level Vue toolbar** that drives the active Quill instance via its API (built-in toolbar module disabled) — bold / italic / link / lists / headings / font as the content model supports. Constraints **C1 (selection/focus preservation), C2 (single shared toolbar + clean instance lifecycle, solved together with Phase 2's refresh-suppression), C3 (font-whitelist registration + link popover)** are implemented from the start, not retrofitted. The Inspector retains only **non-text** style (appearance / layout / colour). The *engineering* is settled and objective — verify it yourself (Playwright: apply each format, selection survives the click, state reflects on `editor-change`). The *look and feel* is a user-judgment surface: build the working candidate, then **pause and put it in front of the user** before widening to more widgets.

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
- **New Playwright:** Phase 1 interaction model (handle reorder, body-click select, Edit-affordance opens Inspector, old whole-panel select gone); Phase 2 inline-edit save round-trip + the two negative gates; Phase 2b column/repeater add-reorder-delete persistence; Phase 3 each format applies with selection surviving the toolbar click; Phase 4 the builder↔public parity harness (standing).
- **Manual (user judgment):** the Phase 3 toolbar **look and feel only** (the editor choice and engineering are settled per the Decided design section — not a user decision). Paused for at the Phase 3 handoff. Pest and Playwright run sequentially, never in parallel.

---

## Closing steps

Follow the close gate in the base prompt. Session-specific:

- **Log file:** `sessions/NNN. Page Builder — In-Page Text Editing, Handles & Inline Formatting — Log.md`.
- **Artifact:** the new interaction model; inline editing live on the safe widget set behind the capability gate; the contextual formatting affordance + the recorded Quill-vs-alternative decision; the builder↔public parity harness; new Pest/Playwright; `VERSION` `0.NNN.x`; any carry-forward to a successor session if split.
- **Branch:** `session-NNN/N` (final iteration), once numbered.
- **Next session:** the deferred phase-slice if split per Open Q3, drafted only when the user names it.
